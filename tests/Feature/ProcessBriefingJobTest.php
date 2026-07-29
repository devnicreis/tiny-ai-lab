<?php

namespace Tests\Feature;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Exceptions\N8nConnectionException;
use App\Jobs\ProcessBriefingJob;
use App\Models\Execution;
use App\Services\N8nService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * Transição `queued` → `processing` e despacho ao n8n (SAD 8.3).
 *
 * O Job é exercitado de ponta a ponta com o N8nService real sobre `Http::fake()`:
 * o que precisa ser garantido é o par persistência + saída HTTP, não a
 * interação com um dublê.
 */
class ProcessBriefingJobTest extends TestCase
{
    use RefreshDatabase;

    private const N8N_URL = 'https://n8n.tinyailab.test/webhook/pipeline';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.n8n.url' => self::N8N_URL,
            'services.n8n.secret' => 'shared-secret-que-nunca-pode-vazar',
            'app.url' => 'https://app.tinyailab.test',
        ]);

        Http::preventStrayRequests();
    }

    private function execution(array $attributes = []): Execution
    {
        return Execution::factory()->create(array_merge([
            'pipeline_type' => 'briefing_generation',
            'status' => 'queued',
            'credits_consumed' => 5,
        ], $attributes));
    }

    public function test_job_runs_on_the_default_queue_with_the_documented_retry_policy(): void
    {
        $job = new ProcessBriefingJob(154);

        $this->assertSame('default', $job->queue);
        $this->assertSame(154, $job->executionId);
        $this->assertSame(3, $job->tries);
        $this->assertSame(30, $job->timeout);
        $this->assertSame([60, 300, 900], $job->backoff);
    }

    public function test_job_is_queued_with_only_the_execution_id(): void
    {
        Queue::fake();

        $execution = $this->execution();

        ProcessBriefingJob::dispatch($execution->id);

        Queue::assertPushedOn('default', ProcessBriefingJob::class, function (ProcessBriefingJob $job) use ($execution): bool {
            return $job->executionId === $execution->id;
        });
    }

    public function test_job_sets_status_processing_and_dispatches_n8n(): void
    {
        Event::fake([ExecutionUpdated::class, ExecutionFailed::class]);
        Http::fake([self::N8N_URL => Http::response(['received' => true])]);

        $execution = $this->execution();

        (new ProcessBriefingJob($execution->id))->handle(app(N8nService::class));

        $this->assertSame('processing', $execution->fresh()->status);
        $this->assertDatabaseHas('executions', [
            'id' => $execution->id,
            'status' => 'processing',
        ]);

        Http::assertSent(function (Request $request) use ($execution): bool {
            return $request->url() === self::N8N_URL
                && $request->data()['execution_id'] === $execution->id;
        });

        Event::assertDispatched(ExecutionUpdated::class, function (ExecutionUpdated $event) use ($execution): bool {
            // O broadcast só pode ocorrer depois da persistência (SAD 9.11).
            return $event->execution->id === $execution->id
                && $event->execution->status === 'processing';
        });
        Event::assertNotDispatched(ExecutionFailed::class);
    }

    public function test_handle_propagates_the_n8n_failure_so_the_worker_can_retry(): void
    {
        Event::fake([ExecutionUpdated::class]);
        Http::fake([self::N8N_URL => Http::response(['error' => 'boom'], 500)]);

        $execution = $this->execution();

        $this->expectException(N8nConnectionException::class);

        try {
            (new ProcessBriefingJob($execution->id))->handle(app(N8nService::class));
        } finally {
            // O estado final pertence ao callback do n8n; o Job apenas retenta.
            $this->assertSame('processing', $execution->fresh()->status);
        }
    }

    public function test_handle_never_reopens_an_execution_already_finalized_by_the_callback(): void
    {
        Event::fake([ExecutionUpdated::class]);
        Http::fake();

        $execution = $this->execution(['status' => 'completed', 'completed_at' => now()]);

        (new ProcessBriefingJob($execution->id))->handle(app(N8nService::class));

        $this->assertSame('completed', $execution->fresh()->status);
        Http::assertNothingSent();
        Event::assertNotDispatched(ExecutionUpdated::class);
    }

    public function test_failed_marks_execution_failed_and_broadcasts(): void
    {
        Event::fake([ExecutionFailed::class]);

        $execution = $this->execution(['status' => 'processing']);

        (new ProcessBriefingJob($execution->id))->failed(
            N8nConnectionException::unreachable($execution->id, 'ConnectionException'),
        );

        $execution->refresh();

        $this->assertSame('failed', $execution->status);
        $this->assertNotNull($execution->failed_at);
        $this->assertNotEmpty($execution->error_message);
        $this->assertNull($execution->result);

        Event::assertDispatched(ExecutionFailed::class, function (ExecutionFailed $event) use ($execution): bool {
            return $event->execution->id === $execution->id
                && $event->execution->status === 'failed';
        });
    }

    public function test_failed_logs_only_the_execution_id(): void
    {
        Event::fake([ExecutionFailed::class]);
        Log::spy();

        $execution = $this->execution(['status' => 'processing']);

        (new ProcessBriefingJob($execution->id))->failed(
            new RuntimeException('cURL error 7: Failed to connect to '.self::N8N_URL),
        );

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($execution): bool {
                $this->assertSame(['execution_id' => $execution->id], $context);
                $this->assertStringNotContainsString(self::N8N_URL, $message);

                return true;
            });
    }

    public function test_failed_never_overwrites_a_diagnosis_written_by_the_callback(): void
    {
        Event::fake([ExecutionFailed::class]);

        $execution = $this->execution([
            'status' => 'failed',
            'error_message' => 'Diagnóstico devolvido pelo n8n.',
            'failed_at' => now(),
        ]);

        (new ProcessBriefingJob($execution->id))->failed(new RuntimeException('boom'));

        $this->assertSame('Diagnóstico devolvido pelo n8n.', $execution->fresh()->error_message);
        Event::assertNotDispatched(ExecutionFailed::class);
    }

    public function test_failed_survives_a_missing_execution(): void
    {
        Event::fake([ExecutionFailed::class]);

        (new ProcessBriefingJob(999999))->failed(new RuntimeException('boom'));

        Event::assertNotDispatched(ExecutionFailed::class);
    }
}
