<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Execution\IndexExecutionRequest;
use App\Http\Requests\Execution\StoreBriefingRequest;
use App\Models\Execution;
use App\Services\ExecutionService;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
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
    public function __construct(
        private readonly OrganizationContext $context,
    ) {}

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
     * Execuções da Brand ativa, dentro da organização ativa (SAD 5.12).
     *
     * Os dois recortes são simultâneos e nenhum deles é `user_id`: a Brand
     * delimita o dado de domínio e a organização delimita o que a sessão pode
     * enxergar. Uma execução disparada por outro membro, na mesma Brand,
     * aparece aqui — é trabalho da organização (SAD 20.6).
     */
    public function index(IndexExecutionRequest $request): JsonResponse
    {
        $executions = Execution::query()
            ->select(self::LIST_COLUMNS)
            ->forOrganization($this->context->currentOrFail()->id)
            ->forBrand($request->brandId())
            ->latest('id')
            ->paginate($request->perPage());

        return response()->json($executions);
    }

    /**
     * Cria a Execution, debita o crédito e enfileira o pipeline.
     *
     * Toda a regra — autorização, verificação de saldo, transação de débito e
     * despacho do Job — pertence ao ExecutionService, compartilhado com o
     * BriefingForm: o Controller apenas traduz o resultado em HTTP.
     */
    public function store(StoreBriefingRequest $request, ExecutionService $executions): JsonResponse
    {
        $execution = $executions->create(
            user: $request->user(),
            brand: $request->brand(),
            // A organização ativa viaja junto para que o Service reprove uma
            // Brand de outra organização (CLAUDE.md regra 34). Quem paga
            // continua sendo a organização dona da Brand, derivada dela.
            activeOrganization: $this->context->currentOrFail(),
            pipeline: (string) $request->validated('pipeline_type'),
            config: $request->config(),
            // A flag vem do reenvio que o usuário confirmou no modal do 409 e
            // de lugar nenhum mais: o Controller apenas a repassa, jamais a
            // presume (SAD 12.19).
            confirmPersonalCredits: $request->confirmsPersonalCredits(),
        );

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
