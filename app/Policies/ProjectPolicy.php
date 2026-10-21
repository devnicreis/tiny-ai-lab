<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Project;
use App\Models\User;
use App\Services\OrganizationContext;

/**
 * Autorização sobre as Brands (SAD 5.9 e 20.2).
 *
 * A pergunta é sempre a mesma: o usuário é membro da **organização dona** da
 * Brand? Não existe Brand "do usuário" — `projects.created_by_user_id` registra
 * quem a criou e é dado de auditoria, jamais critério de autorização (SAD 20.2).
 *
 * > 🔁 A verificação por `projects.user_id` que existia aqui foi substituída
 * > pela verificação de participação. `user_id` deixou de ser critério de
 * > isolamento (SAD 20.6).
 *
 * Enxergar a Brand é de **qualquer membro**. Criar, editar — inclusive o
 * Brand Core — e excluir são de Owner e Admin, pelo `canManageBrands()` de
 * `OrganizationRole` (SAD 5.9 e 20.4). Um Member roda as ferramentas de IA nas
 * Marcas, mas não altera a Brand Memory.
 *
 * > 🔁 SUPERSEDED (ADR-010): gerenciar Marcas era capacidade de qualquer
 * > membro. Member deixou de criar, editar e excluir Marcas.
 */
class ProjectPolicy
{
    public function __construct(
        private readonly OrganizationContext $context,
    ) {}

    /**
     * Criar uma Brand exige papel que gerencie Marcas na organização ativa.
     *
     * A Brand nova nasce na organização ativa da sessão — não há outro destino
     * possível, porque não existe marca órfã (SAD 20.2). Limites comerciais de
     * quantidade de Marcas por plano são outro eixo e não são avaliados aqui.
     */
    public function create(User $user): bool
    {
        $organization = $this->context->current();

        return $organization !== null
            && ($user->roleIn($organization)?->canManageBrands() ?? false);
    }

    public function view(User $user, Project $project): bool
    {
        return $this->roleInOwner($user, $project) !== null;
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canManageBrandsOf($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->canManageBrandsOf($user, $project);
    }

    public function restore(User $user, Project $project): bool
    {
        return $this->canManageBrandsOf($user, $project);
    }

    private function canManageBrandsOf(User $user, Project $project): bool
    {
        return $this->roleInOwner($user, $project)?->canManageBrands() ?? false;
    }

    /**
     * O papel do usuário na organização dona da Brand, ou `null` para quem não
     * participa dela.
     *
     * A organização é carregada da própria Brand, e não do contexto de sessão:
     * uma Execution ou uma Brand aberta por link direto continua acessível a
     * quem é membro da organização dela, ainda que a organização ativa no
     * momento seja outra. O recorte por contexto ativo é das **consultas**
     * (SAD 20.6); a Policy responde por participação.
     */
    private function roleInOwner(User $user, Project $project): ?OrganizationRole
    {
        $organization = $project->organization()->first();

        return $organization !== null ? $user->roleIn($organization) : null;
    }
}
