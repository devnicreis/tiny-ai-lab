<?php

namespace App\Events;

use App\Models\Execution;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Sinaliza à interface que o estado de uma Execution mudou (SAD 9.6).
 *
 * O evento apenas avisa que houve alteração: `result`, `config` e qualquer
 * conteúdo JSONB permanecem fora do payload e são carregados depois pelo
 * componente Livewire, por consulta autorizada ao Laravel.
 *
 * Deve ser disparado somente após a persistência ter sido confirmada
 * (SAD 9.11 — persistência → commit → broadcast).
 */
class ExecutionUpdated implements ShouldBroadcast
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
            'status' => $this->execution->status,
            'pipeline_type' => $this->execution->pipeline_type,
            'updated_at' => $this->execution->updated_at->toIso8601String(),
        ];
    }
}
