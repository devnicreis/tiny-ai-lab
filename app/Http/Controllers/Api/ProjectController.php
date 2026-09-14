<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;
use App\Services\OrganizationContext;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD das Brands (SAD 20.6).
 *
 * Toda consulta é escopada pela **organização ativa**, nunca por `user_id`: não
 * existe Brand do usuário, e quem participa da organização enxerga todas as
 * Marcas dela. O contexto chega pelo middleware `organization`.
 */
class ProjectController extends Controller
{
    public function __construct(
        private readonly OrganizationContext $context,
    ) {}

    /**
     * Brands da organização ativa.
     *
     * O escopo por `organization_id` substitui integralmente o antigo escopo
     * por `user_id` (SAD 20.6). A hierarquia de sub-brands não amplia esta
     * listagem: ela é apresentação, não escopo (CLAUDE.md regra 29).
     */
    public function index(): JsonResponse
    {
        $projects = Project::query()
            ->forOrganization($this->context->currentOrFail()->id)
            ->latest()
            ->paginate(15);

        return response()->json($projects);
    }

    /**
     * Cria a Brand dentro da organização ativa.
     *
     * A organização vem do contexto e nunca do corpo da requisição: aceitar um
     * `organization_id` do cliente permitiria criar Marca em organização alheia.
     * `created_by_user_id` é gravado como provenance — quem criou —, jamais como
     * propriedade (SAD 20.2).
     */
    public function store(StoreProjectRequest $request): JsonResponse
    {
        $project = Project::create([
            ...$request->validated(),
            'organization_id' => $this->context->currentOrFail()->id,
            'created_by_user_id' => $request->user()->id,
        ]);

        return response()->json($project, Response::HTTP_CREATED);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return response()->json($project);
    }

    public function update(UpdateProjectRequest $request, Project $project): JsonResponse
    {
        $project->update($request->validated());

        return response()->json($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        $project->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
