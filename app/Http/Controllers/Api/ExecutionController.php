<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientCreditsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Execution\IndexExecutionRequest;
use App\Http\Requests\Execution\StoreBriefingRequest;
use App\Jobs\ProcessBriefingJob;
use App\Models\Execution;
use App\Services\CreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Porta de entrada do pipeline (SAD 6.1).
 *
 * Nenhuma requisição HTTP espera pela IA: o Controller valida, autoriza,
 * registra a Execution, debita o crédito e devolve 202 — o processamento segue
 * na fila e a conclusão chega pelo callback do n8n.
 */
class ExecutionController extends Controller
{
    /**
     * Colunas expostas em listagem (SAD L.5).
     *
     * `result` e `config` são JSONB e ficam permanentemente fora daqui: uma
     * página de 15 execuções de Brand Research carregaria megabytes de
     * diagnóstico para exibir uma lista de status (CLAUDE.md regra 11).
     *
     * @var array<int, string>
     */
    private const LIST_COLUMNS = [
        'id',
        'project_id',
        'pipeline_type',
        'status',
        'credits_consumed',
        'tokens_used',
        'error_message',
        'completed_at',
        'failed_at',
        'created_at',
    ];

    /**
     * Execuções da Brand ativa, escopadas também por `user_id` (SAD 5.12).
     */
    public function index(IndexExecutionRequest $request): JsonResponse
    {
        $executions = Execution::query()
            ->select(self::LIST_COLUMNS)
            ->where('user_id', $request->user()->id)
            ->forBrand($request->brandId())
            ->latest('id')
            ->paginate($request->perPage());

        return response()->json($executions);
    }

    /**
     * Cria a Execution, debita o crédito e enfileira o pipeline.
     *
     * O débito e a criação compartilham uma transação porque a corrida entre
     * duas requisições simultâneas só é resolvida dentro de `deduct()`, sob
     * lock: se o saldo real não cobrir o custo, a Execution criada instantes
     * antes precisa desaparecer junto — SAD 12.7 exige que saldo insuficiente
     * não deixe execução nenhuma para trás.
     *
     * O Job é despachado apenas após o commit: um Worker que lesse a Execution
     * antes dela existir no banco falharia em `findOrFail`.
     */
    public function store(StoreBriefingRequest $request, CreditService $credits): JsonResponse
    {
        $user = $request->user();
        $pipeline = (string) $request->validated('pipeline_type');

        if (! $credits->canExecute($user, $pipeline)) {
            throw InsufficientCreditsException::for($user, $pipeline, $credits->costFor($pipeline));
        }

        $execution = DB::transaction(function () use ($request, $credits, $user, $pipeline): Execution {
            $execution = Execution::create([
                'project_id' => $request->brand()->id,
                'user_id' => $user->id,
                'pipeline_type' => $pipeline,
                'status' => 'queued',
                'config' => $request->config(),
                'credits_consumed' => $credits->costFor($pipeline),
            ]);

            $credits->deduct($user, $pipeline);

            return $execution;
        });

        ProcessBriefingJob::dispatch($execution->id);

        return response()->json([
            'execution_id' => $execution->id,
            'status' => $execution->status,
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Detalhe completo, incluindo `result` — o único endpoint autorizado a
     * devolvê-lo.
     */
    public function show(Execution $execution): JsonResponse
    {
        $this->authorize('view', $execution);

        return response()->json($execution);
    }
}
