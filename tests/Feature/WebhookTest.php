<?php

namespace Tests\Feature;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Http\Middleware\VerifyN8nSecret;
use App\Models\Execution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Concerns\InteractsWithOrganizations;
use Tests\TestCase;

/**
 * Callback do n8n (SAD 5.6, 7.4 e 7.10 — Regra 7).
 *
 * O endpoint não possui usuário autenticado: sua única credencial é o segredo
 * compartilhado no header `X-N8N-Secret`.
 */
class WebhookTest extends TestCase
{
    use InteractsWithOrganizations, RefreshDatabase;

    private const ENDPOINT = '/api/webhook/n8n/result';

    private const SECRET = 'segredo-compartilhado-de-teste';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.n8n.secret' => self::SECRET]);
    }

    /**
     * @return array<string, string>
     */
    private function n8nHeaders(string $secret = self::SECRET): array
    {
        return ['X-N8N-Secret' => $secret];
    }

    private function processingExecution(): Execution
    {
        $user = User::factory()->create();
        $project = $this->brandOf($this->organizationWith($user));

        return Execution::factory()->processing()->create([
            'project_id' => $project->id,
            'user_id' => $user->id,
            'pipeline_type' => 'briefing_generation',
        ]);
    }

    public function test_webhook_saves_result_and_broadcasts(): void
    {
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);

        $execution = $this->processingExecution();

        $result = [
            'diagnosis' => ['posicionamento' => 'premium'],
            'calendar' => [['day' => 1, 'theme' => 'lançamento']],
        ];

        $this->postJson(self::ENDPOINT, [
            'execution_id' => $execution->id,
            'status' => 'completed',
            'pipeline_type' => 'briefing_generation',
            'tokens_used' => 18432,
            'credits_consumed' => 5,
            'result' => $result,
            'finished_at' => '2026-07-01T15:37:42Z',
            'error' => null,
        ], $this->n8nHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $execution->refresh();

        $this->assertSame('completed', $execution->status);
        $this->assertSame($result, $execution->result);
        $this->assertSame(18432, $execution->tokens_used);
        $this->assertNotNull($execution->completed_at);
        $this->assertSame('2026-07-01 15:37:42', $execution->completed_at->utc()->toDateTimeString());
        $this->assertNull($execution->error_message);

        Event::assertDispatched(
            ExecutionUpdated::class,
            fn (ExecutionUpdated $event) => $event->execution->id === $execution->id,
        );
        Event::assertNotDispatched(ExecutionFailed::class);
    }

    public function test_webhook_never_overwrites_the_credits_debited_by_laravel(): void
    {
        $execution = $this->processingExecution();
        $debited = $execution->credits_consumed;

        $this->postJson(self::ENDPOINT, [
            'execution_id' => $execution->id,
            'status' => 'completed',
            'result' => ['ok' => true],
            // O n8n não decide contabilidade: o débito veio de plan_limits.
            'credits_consumed' => 9999,
        ], $this->n8nHeaders())->assertOk();

        $this->assertSame($debited, $execution->refresh()->credits_consumed);
    }

    public function test_webhook_marks_execution_as_failed(): void
    {
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);

        $execution = $this->processingExecution();

        $this->postJson(self::ENDPOINT, [
            'execution_id' => $execution->id,
            'status' => 'failed',
            'error' => ['message' => 'Pipeline timeout'],
        ], $this->n8nHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'failed');

        $execution->refresh();

        $this->assertSame('failed', $execution->status);
        $this->assertSame('Pipeline timeout', $execution->error_message);
        $this->assertNotNull($execution->failed_at);
        // O campo result só existe após conclusão bem-sucedida (CLAUDE.md §6).
        $this->assertNull($execution->result);

        Event::assertDispatched(ExecutionFailed::class);
        Event::assertNotDispatched(ExecutionUpdated::class);
    }

    public function test_invalid_n8n_secret_returns_403(): void
    {
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);

        $execution = $this->processingExecution();

        $this->postJson(self::ENDPOINT, [
            'execution_id' => $execution->id,
            'status' => 'completed',
            'result' => ['forjado' => true],
        ], $this->n8nHeaders('segredo-errado'))
            ->assertForbidden()
            ->assertJsonPath('error', 'invalid_secret');

        $this->assertSame('processing', $execution->refresh()->status);
        $this->assertNull($execution->result);
        Event::assertNothingDispatched();
    }

    public function test_missing_n8n_secret_returns_403(): void
    {
        $execution = $this->processingExecution();

        $this->postJson(self::ENDPOINT, [
            'execution_id' => $execution->id,
            'status' => 'completed',
        ])
            ->assertForbidden()
            ->assertJsonPath('error', 'invalid_secret');

        $this->assertSame('processing', $execution->refresh()->status);
    }

    public function test_webhook_requires_execution_id_and_status(): void
    {
        $this->postJson(self::ENDPOINT, [], $this->n8nHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['execution_id', 'status']);
    }

    public function test_webhook_rejects_a_non_terminal_status(): void
    {
        $execution = $this->processingExecution();

        $this->postJson(self::ENDPOINT, [
            'execution_id' => $execution->id,
            'status' => 'processing',
        ], $this->n8nHeaders())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_duplicate_callback_does_not_overwrite_a_finished_execution(): void
    {
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);

        $execution = $this->processingExecution();

        $payload = [
            'execution_id' => $execution->id,
            'status' => 'completed',
            'result' => ['diagnosis' => 'original'],
        ];

        $this->postJson(self::ENDPOINT, $payload, $this->n8nHeaders())->assertOk();

        $this->postJson(self::ENDPOINT, [
            ...$payload,
            'result' => ['diagnosis' => 'retry vazio'],
        ], $this->n8nHeaders())
            ->assertOk()
            ->assertJsonPath('ignored', true);

        $this->assertSame(['diagnosis' => 'original'], $execution->refresh()->result);
        Event::assertDispatchedTimes(ExecutionUpdated::class, 1);
    }

    public function test_webhook_route_has_no_ip_rate_limiting(): void
    {
        // CLAUDE.md §5: a segurança do callback depende exclusivamente do
        // segredo compartilhado; um limite por origem descartaria resultados
        // legítimos já processados e já cobrados.
        $middleware = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/webhook/n8n/result')
            ->gatherMiddleware();

        $this->assertNotEmpty(array_filter(
            $middleware,
            fn ($name) => $name === VerifyN8nSecret::class || $name === 'verify.n8n',
        ));

        foreach ($middleware as $name) {
            $this->assertStringNotContainsString('throttle', (string) $name);
        }
    }
}
