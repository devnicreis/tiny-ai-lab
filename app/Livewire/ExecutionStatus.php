<?php

namespace App\Livewire;

use App\Models\Execution;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Acompanhamento em tempo real do estado de uma Execution (SAD 9.6 e M.5).
 *
 * Assim que o Job é despachado, é este componente — não o botão do formulário —
 * que representa o progresso (SAD M.4). Ele reage apenas a metadados: `result`
 * jamais trafega no broadcast nem é lido aqui. Quem renderiza o resultado é o
 * ResultDashboard, avisado por evento Livewire (SAD M.10).
 */
class ExecutionStatus extends Component
{
    /**
     * Colunas de metadado que a tela precisa. `result` e `config` ficam fora
     * deliberadamente (CLAUDE.md regra 11).
     *
     * @var array<int, string>
     */
    private const STATUS_COLUMNS = [
        'id',
        'project_id',
        'status',
        'pipeline_type',
        'error_message',
        'completed_at',
        'failed_at',
    ];

    public int $executionId;

    public string $status = 'queued';

    public string $pipelineType = '';

    public ?string $errorMessage = null;

    public function mount(int $executionId): void
    {
        $this->executionId = $executionId;

        $this->pullStatus();
    }

    /**
     * Reage ao broadcast do canal privado da Execution.
     *
     * O nome do canal coincide exatamente com `routes/channels.php`
     * (`execution.{executionId}`), conforme SAD M.5. O payload traz apenas
     * status e timestamps; nada mais é consultado no banco a partir daqui —
     * buscar `result` no listener é proibido.
     *
     * @param  array<string, mixed>  $event
     */
    #[On('echo-private:execution.{executionId},ExecutionUpdated')]
    public function onExecutionUpdated(array $event): void
    {
        $this->status = (string) ($event['status'] ?? $this->status);

        if ($this->isTerminal()) {
            // O resultado pertence a outro componente: avisa-se apenas que ele
            // já pode carregá-lo por consulta autorizada (SAD M.10).
            $this->dispatch('execution-finished', executionId: $this->executionId);
        }
    }

    /**
     * Fallback de polling, acionado pela view apenas enquanto o estado não é
     * final e somente para cobrir indisponibilidade do Reverb (SAD M.5).
     */
    public function refreshStatus(): void
    {
        $before = $this->status;

        $this->pullStatus();

        if ($this->status !== $before && $this->isTerminal()) {
            $this->dispatch('execution-finished', executionId: $this->executionId);
        }
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, Execution::TERMINAL_STATUSES, true);
    }

    public function render()
    {
        return view('livewire.execution-status');
    }

    /**
     * Lê os metadados do estado atual, escopado às organizações do usuário.
     *
     * O recorte por participação não substitui a ExecutionPolicy — aplicada na
     * rota `/result/{execution}` — apenas garante que uma requisição Livewire
     * forjada com outro identificador não devolva metadado algum. `user_id`
     * não serve a esse recorte: a Execution é da organização, e um membro
     * acompanha o que outro disparou na mesma Brand (SAD 20.6).
     */
    private function pullStatus(): void
    {
        $execution = Execution::query()
            ->select(self::STATUS_COLUMNS)
            ->whereKey($this->executionId)
            ->visibleTo(auth()->user())
            ->firstOrFail();

        $this->status = (string) $execution->status;
        $this->pipelineType = (string) $execution->pipeline_type;
        $this->errorMessage = $execution->error_message;
    }
}
