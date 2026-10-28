<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * A Brand ativa da sessão (SAD 20.5 e M.8).
 *
 * Segunda variável do contexto, ao lado da organização ativa: esta classe é a
 * única autoridade sobre `active_project_id`, assim como `OrganizationContext`
 * é sobre `active_organization_id`. Até a Sessão 3.2 a chave só era *limpa*
 * (na troca de organização) e nunca lida — nada validava que ela pertencesse
 * à organização ativa.
 *
 * ## O pertencimento é reconferido a cada leitura
 *
 * `current()` nunca confia no identificador da sessão: a Brand é procurada
 * **dentro da organização ativa**. Uma Brand de outra organização — mesmo de
 * uma da qual o usuário também é membro —, excluída ou inexistente é estado
 * inválido, e o contexto de Brand desaparece em vez de ser "quase certo"
 * (SAD 20.5). A organização é verificada antes, por `OrganizationContext`.
 *
 * `scoped`, nunca singleton, pelo mesmo motivo do `OrganizationContext`: o
 * contexto é da requisição.
 */
class BrandContext
{
    public function __construct(
        private readonly OrganizationContext $organizations,
    ) {}

    /**
     * A Brand ativa, validada contra a organização ativa, ou `null`.
     */
    public function current(): ?Project
    {
        $organization = $this->organizations->current();
        $projectId = session(OrganizationContext::BRAND_SESSION_KEY);

        if ($organization === null || ! is_numeric($projectId)) {
            return null;
        }

        $project = Project::query()
            ->forOrganization((int) $organization->getKey())
            ->find((int) $projectId);

        if ($project === null) {
            session()->forget(OrganizationContext::BRAND_SESSION_KEY);
        }

        return $project;
    }

    /**
     * A Brand ativa ou a única Brand da organização ativa.
     *
     * A seleção automática só acontece sem ambiguidade — exatamente uma Brand —,
     * o mesmo critério de `OrganizationContext::resolve()`. Com duas ou mais, o
     * contexto fica vazio até o usuário escolher (SAD 20.5).
     */
    public function resolve(): ?Project
    {
        $current = $this->current();

        if ($current !== null) {
            return $current;
        }

        $organization = $this->organizations->current();

        if ($organization === null) {
            return null;
        }

        $brands = Project::query()
            ->forOrganization((int) $organization->getKey())
            ->limit(2)
            ->get();

        if ($brands->count() !== 1) {
            return null;
        }

        $this->set($brands->first());

        return $brands->first();
    }

    /**
     * Define a Brand ativa.
     *
     * Duas verificações, ambas obrigatórias: a `ProjectPolicy` (o usuário é
     * membro da organização dona) e o pertencimento à organização **ativa**. A
     * Policy sozinha aceitaria a Brand de outra organização do mesmo usuário,
     * e o contexto ficaria com Brand e organização desencontradas (SAD 20.5).
     *
     * @throws AuthorizationException
     */
    public function set(Project $project): void
    {
        $user = Auth::user();
        $organization = $this->organizations->current();

        if (! $user instanceof User
            || $organization === null
            || (int) $project->organization_id !== (int) $organization->getKey()
            || Gate::forUser($user)->denies('view', $project)) {
            throw new AuthorizationException('The brand does not belong to the active organization.');
        }

        session([OrganizationContext::BRAND_SESSION_KEY => $project->getKey()]);
    }

    public function clear(): void
    {
        session()->forget(OrganizationContext::BRAND_SESSION_KEY);
    }
}
