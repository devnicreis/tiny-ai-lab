<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

/**
 * Autorização sobre a Organização (SAD 20.4).
 *
 * A matriz tem exatamente **dois papéis** — `admin` e `member` — e nenhum
 * terceiro pode ser inferido aqui. Tudo o que é faturamento, participação ou
 * existência da organização é de Admin; o que é trabalho — Marcas e execuções —
 * é de qualquer membro e vive na ProjectPolicy e na ExecutionPolicy.
 *
 * `users.is_admin` **nunca** aparece nesta classe. Staff da plataforma é o eixo
 * global do painel `/admin/*` e não concede papel algum em organização de
 * cliente; usar um eixo como atalho para o outro é falha de segurança
 * (SAD 20.7).
 */
class OrganizationPolicy
{
    /**
     * Enxergar a organização é capacidade de qualquer membro.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user, $organization);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user, $organization);
    }

    /**
     * Assinar, trocar e cancelar plano (SAD 20.4 e 12.18).
     */
    public function manageBilling(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user, $organization);
    }

    /**
     * Convidar, remover e alterar papéis (SAD 20.4).
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user, $organization);
    }

    /**
     * Comprar créditos **para a organização** (SAD 20.4).
     *
     * Comprar créditos **pessoais** não passa por aqui: a carteira pessoal é do
     * usuário, acompanha-o entre organizações e não depende de papel algum
     * (SAD 12.2).
     */
    public function purchaseForOrganization(User $user, Organization $organization): bool
    {
        return $this->isAdmin($user, $organization);
    }

    /**
     * Remover um membro — nunca o último Admin (SAD 20.4).
     *
     * Sair por conta própria é uma remoção como outra qualquer, e por isso cai
     * na mesma verificação: para deixar a organização, o último Admin precisa
     * antes promover outro membro ou excluir a organização.
     *
     * A Policy é a primeira barreira, não a única. O invariante é de negócio e
     * precisa ser reconferido **dentro da transação que altera a participação,
     * sob lock** (CLAUDE.md regra 32): duas remoções simultâneas dos dois
     * últimos Admins passariam por duas avaliações de Policy igualmente
     * verdadeiras e deixariam a organização órfã.
     */
    public function removeMember(User $user, Organization $organization, User $target): bool
    {
        return $this->isAdmin($user, $organization)
            && $organization->hasMember($target)
            && ! $organization->isLastAdmin($target);
    }

    /**
     * Alterar o papel de um membro — nunca rebaixando o último Admin.
     *
     * Promover a Admin é sempre permitido a um Admin; o invariante só é tocado
     * quando o alvo *deixa* de ser Admin.
     */
    public function changeRole(
        User $user,
        Organization $organization,
        User $target,
        OrganizationRole $newRole,
    ): bool {
        if (! $this->isAdmin($user, $organization) || ! $organization->hasMember($target)) {
            return false;
        }

        if ($newRole === OrganizationRole::Admin) {
            return true;
        }

        return ! $organization->isLastAdmin($target);
    }

    private function isAdmin(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) === OrganizationRole::Admin;
    }
}
