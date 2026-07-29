<?php

namespace Tests\Feature;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Models\Execution;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

/**
 * Contrato dos eventos de broadcast de uma Execution (SAD 9.6 e 9.7).
 *
 * O WebSocket apenas sinaliza mudança de estado: `result` e `config` são
 * carregados depois, por consulta autorizada ao Laravel.
 */
class ExecutionTest extends TestCase
{
    private const EXECUTION_ID = 154;

    private function execution(array $attributes = []): Execution
    {
        $execution = Execution::factory()->make(array_merge([
            'project_id' => 8,
            'user_id' => 12,
            'pipeline_type' => 'brand_research',
            'config' => ['language' => 'pt'],
        ], $attributes));

        $execution->forceFill(['id' => self::EXECUTION_ID]);

        return $execution;
    }

    public function test_execution_updated_broadcasts_on_the_private_execution_channel(): void
    {
        $channels = (new ExecutionUpdated($this->execution()))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-execution.'.self::EXECUTION_ID, (string) $channels[0]);
    }

    public function test_execution_updated_broadcasts_only_state_metadata(): void
    {
        $execution = $this->execution([
            'status' => 'completed',
            'result' => ['diagnosis' => ['segredo do resultado']],
            'completed_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = (new ExecutionUpdated($execution))->broadcastWith();

        $this->assertSame(
            ['execution_id', 'status', 'pipeline_type', 'updated_at'],
            array_keys($payload),
        );
        $this->assertSame(self::EXECUTION_ID, $payload['execution_id']);
        $this->assertSame('completed', $payload['status']);
        $this->assertSame('brand_research', $payload['pipeline_type']);
        $this->assertSame($execution->updated_at->toIso8601String(), $payload['updated_at']);
        $this->assertStringNotContainsString('segredo do resultado', json_encode($payload));
    }

    public function test_execution_failed_broadcasts_on_the_private_execution_channel(): void
    {
        $channels = (new ExecutionFailed($this->execution()))->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-execution.'.self::EXECUTION_ID, (string) $channels[0]);
    }

    public function test_execution_failed_broadcasts_only_the_error_metadata(): void
    {
        $execution = $this->execution([
            'status' => 'failed',
            'error_message' => 'Pipeline timeout',
            'failed_at' => now(),
        ]);

        $payload = (new ExecutionFailed($execution))->broadcastWith();

        $this->assertSame(
            ['execution_id', 'status', 'pipeline_type', 'error_message', 'failed_at'],
            array_keys($payload),
        );
        $this->assertSame(self::EXECUTION_ID, $payload['execution_id']);
        $this->assertSame('failed', $payload['status']);
        $this->assertSame('brand_research', $payload['pipeline_type']);
        $this->assertSame('Pipeline timeout', $payload['error_message']);
        $this->assertSame($execution->failed_at->toIso8601String(), $payload['failed_at']);
    }
}
