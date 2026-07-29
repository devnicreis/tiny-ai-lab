<?php

namespace App\Jobs;

use App\Events\ExecutionFailed;
use App\Events\ExecutionUpdated;
use App\Models\Execution;
use App\Services\N8nService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Leva uma Execution já persistida da fila até o n8n (SAD 8.3).
 *
 * O Job cobre exatamente uma transição de estado: `queued` → `processing`.
 * A conclusão (`completed` ou `failed`) pertence unicamente ao callback
 * autenticado do n8n (SAD 6), de modo que terminar o `handle()` não significa
 * que o pipeline terminou — significa apenas que o Droplet 2 aceitou o
 * despacho.
 *
 * O construtor recebe apenas o identificador (SAD L.8): o Model é reconsultado
 * no Worker, evitando que um payload serializado no Redis fique defasado em
 * relação ao banco entre o enfileiramento e a execução.
 */
class ProcessBriefingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Fila geral de pipelines (SAD 8.2).
     */
    private const QUEUE = 'default';

    /**
     * Estados finais: uma vez alcançados, apenas o callback do n8n os produziu
     * e nenhuma tentativa posterior do Job pode sobrescrevê-los.
     *
     * @var array<int, string>
     */
    private const TERMINAL_STATUSES = ['completed', 'failed'];

    /**
     * Mensagem exibida ao usuário quando o despacho não pôde ser concluído.
     *
     * Deliberadamente genérica: `error_message` trafega no broadcast e chega à
     * interface, enquanto o diagnóstico técnico permanece no log e no relatório
     * de exception do Worker (SAD 6 — preservar diagnóstico sem expor dados
     * sensíveis ao usuário).
     */
    private const USER_FACING_ERROR = 'Não foi possível iniciar o processamento. Tente novamente em alguns minutos.';

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @var array<int, int>
     */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly int $executionId,
    ) {
        $this->onQueue(self::QUEUE);
    }

    public function handle(N8nService $n8n): void
    {
        $execution = Execution::query()->findOrFail($this->executionId);

        if (in_array($execution->status, self::TERMINAL_STATUSES, true)) {
            // O callback chegou antes desta tentativa — provável retry após uma
            // resposta de erro do n8n que, ainda assim, iniciou o workflow.
            Log::info('Execution already finalized, skipping dispatch', [
                'execution_id' => $this->executionId,
            ]);

            return;
        }

        $execution->update(['status' => 'processing']);

        // Persistência → commit → broadcast (SAD 9.11).
        ExecutionUpdated::dispatch($execution);

        // Qualquer falha aqui sobe: o Worker decide entre novo retry e failed().
        $n8n->dispatch($execution);
    }

    /**
     * Última tentativa esgotada (SAD 8.3 — failed()).
     */
    public function failed(?Throwable $exception): void
    {
        // Contexto mínimo: a mensagem do erro de transporte pode carregar a URL
        // do Droplet 2. O trace completo já é reportado pelo Worker.
        Log::error('ProcessBriefingJob failed', ['execution_id' => $this->executionId]);

        $execution = Execution::query()->find($this->executionId);

        if ($execution === null || in_array($execution->status, self::TERMINAL_STATUSES, true)) {
            // Sem Execution ou já finalizada pelo n8n: o diagnóstico do callback
            // é mais preciso que o desta camada e não deve ser sobrescrito.
            return;
        }

        $execution->update([
            'status' => 'failed',
            'error_message' => self::USER_FACING_ERROR,
            'failed_at' => now(),
        ]);

        ExecutionFailed::dispatch($execution);
    }
}
