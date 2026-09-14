<?php

namespace Tests\Unit\Services;

use App\Exceptions\N8nConnectionException;
use App\Models\Execution;
use App\Models\Project;
use App\Services\N8nService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * O Service é a única saída HTTP do Laravel em direção ao n8n (SAD 7).
 *
 * Nenhum teste toca o banco: os models são montados em memória para manter o
 * foco no contrato do payload, nos headers e no tratamento de falhas.
 */
class N8nServiceTest extends TestCase
{
    private const URL = 'https://n8n.tinyailab.test/webhook/pipeline';

    private const SECRET = 'shared-secret-que-nunca-pode-vazar';

    private const EXECUTION_ID = 154;

    private const PROJECT_ID = 8;

    private const USER_ID = 12;

    private const ORGANIZATION_ID = 3;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.n8n.url' => self::URL,
            'services.n8n.secret' => self::SECRET,
            'app.url' => 'https://app.tinyailab.test',
        ]);

        Http::preventStrayRequests();
    }

    private function service(): N8nService
    {
        return app(N8nService::class);
    }

    /**
     * Execution completa em memória, com a Brand já associada.
     */
    private function execution(): Execution
    {
        // `organization_id` é informado para que a factory não precise criar a
        // organização no banco: nenhum teste deste arquivo toca o schema.
        $project = Project::factory()->make([
            'organization_id' => self::ORGANIZATION_ID,
            'created_by_user_id' => self::USER_ID,
            'brand_name' => 'Tiny Coffee',
            'website' => 'https://tinycoffee.test',
            'description' => 'Torrefação artesanal.',
            'segment' => 'food',
            'competitors' => ['https://rival.test'],
            'channels' => ['instagram', 'tiktok'],
        ]);

        $project->forceFill(['id' => self::PROJECT_ID]);

        $execution = Execution::factory()->make([
            'project_id' => self::PROJECT_ID,
            'user_id' => self::USER_ID,
            'pipeline_type' => 'brand_research',
            'status' => 'queued',
            'credits_consumed' => 50,
            'config' => ['language' => 'pt', 'inputs' => ['tone' => 'casual']],
        ]);

        $execution->forceFill(['id' => self::EXECUTION_ID]);
        $execution->setRelation('project', $project);

        return $execution;
    }

    public function test_payload_contains_required_fields(): void
    {
        Http::fake([self::URL => Http::response(['received' => true])]);

        $this->service()->dispatch($this->execution());

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();

            $this->assertSame(self::URL, $request->url());
            $this->assertSame('POST', $request->method());

            $this->assertSame(self::EXECUTION_ID, $payload['execution_id']);
            $this->assertSame('brand_research', $payload['pipeline_type']);
            $this->assertSame(self::USER_ID, $payload['user_id']);
            $this->assertSame(self::PROJECT_ID, $payload['project_id']);
            $this->assertSame(50, $payload['credits_cost']);
            $this->assertSame('pt', $payload['locale']);
            $this->assertSame(['language' => 'pt', 'inputs' => ['tone' => 'casual']], $payload['config']);
            $this->assertSame(
                'https://app.tinyailab.test/api/webhook/n8n/result',
                $payload['webhook_callback'],
            );
            $this->assertNotEmpty($payload['requested_at']);

            $this->assertSame(self::PROJECT_ID, $payload['brand']['id']);
            $this->assertSame('Tiny Coffee', $payload['brand']['name']);
            $this->assertSame('food', $payload['brand']['segment']);
            $this->assertSame(['instagram', 'tiktok'], $payload['brand']['channels']);

            return true;
        });
    }

    public function test_dispatch_authenticates_with_the_shared_secret_header(): void
    {
        Http::fake([self::URL => Http::response(['received' => true])]);

        $this->service()->dispatch($this->execution());

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('X-N8N-Secret', self::SECRET)
                && $request->hasHeader('User-Agent', 'TinyAILab/Laravel')
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_payload_never_carries_the_shared_secret(): void
    {
        Http::fake([self::URL => Http::response(['received' => true])]);

        $this->service()->dispatch($this->execution());

        Http::assertSent(function (Request $request): bool {
            $this->assertStringNotContainsString(self::SECRET, $request->body());

            return true;
        });
    }

    public function test_connection_failure_throws_exception(): void
    {
        // A mensagem do cliente HTTP carrega a URL completa do Droplet 2.
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 7: Failed to connect to '.self::URL
        ));

        try {
            $this->service()->dispatch($this->execution());
            $this->fail('N8nConnectionException was not thrown.');
        } catch (N8nConnectionException $e) {
            $this->assertSame(self::EXECUTION_ID, $e->executionId);
            $this->assertSame(ConnectionException::class, $e->reason);
            $this->assertNull($e->getPrevious());
            $this->assertStringNotContainsString(self::URL, $e->getMessage());
        }
    }

    public function test_error_response_throws_exception(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'boom'], 500)]);

        try {
            $this->service()->dispatch($this->execution());
            $this->fail('N8nConnectionException was not thrown.');
        } catch (N8nConnectionException $e) {
            $this->assertSame(self::EXECUTION_ID, $e->executionId);
            $this->assertSame(500, $e->statusCode);
        }
    }

    public function test_missing_configuration_throws_exception_without_calling_n8n(): void
    {
        config(['services.n8n.secret' => null]);

        Http::fake();

        $this->expectException(N8nConnectionException::class);

        try {
            $this->service()->dispatch($this->execution());
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_successful_dispatch_logs_only_the_execution_id(): void
    {
        Http::fake([self::URL => Http::response(['received' => true])]);

        Log::spy();

        $this->service()->dispatch($this->execution());

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $this->assertSame(['execution_id' => self::EXECUTION_ID], $context);
                $this->assertStringNotContainsString(self::SECRET, $message);
                $this->assertStringNotContainsString(self::URL, $message);

                return true;
            });
    }

    public function test_reported_exception_never_leaks_the_secret_or_the_url(): void
    {
        Log::spy();

        report(N8nConnectionException::unreachable(
            self::EXECUTION_ID,
            ConnectionException::class,
        ));

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                $logged = $message.json_encode($context);

                $this->assertStringNotContainsString(self::SECRET, $logged);
                $this->assertStringNotContainsString(self::URL, $logged);
                $this->assertSame(self::EXECUTION_ID, $context['execution_id']);

                return true;
            });
    }
}
