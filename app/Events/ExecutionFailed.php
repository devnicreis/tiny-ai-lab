<?php

namespace App\Events;

use App\Models\Execution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Informa imediatamente à interface que um pipeline falhou (SAD 9.7).
 *
 * Trafega pelo mesmo canal privado da Execution e carrega apenas a mensagem de
 * erro já tratada em `executions.error_message` — nunca o payload bruto
 * devolvido pelo n8n nem qualquer conteúdo de `result`.
 */
class ExecutionFailed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Execution $execution,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('execution.'.$this->execution->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'execution_id' => $this->execution->id,
            'status' => 'failed',
            'pipeline_type' => $this->execution->pipeline_type,
            'error_message' => $this->execution->error_message,
            'failed_at' => $this->execution->failed_at?->toIso8601String(),
        ];
    }
}
