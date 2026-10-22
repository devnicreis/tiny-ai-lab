<?php

namespace App\Policies;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

/**
 * Autorização sobre a Organização (SAD 20.4, ADR-010).
 *
 * Três papéis, e toda decisão é delegada aos métodos `can*()` de
 * `OrganizationRole` — a única fonte da matriz. Faturamento, compra para a org,
 * papéis, transferência de posse e exclusão são do Owner; gerenciar membros e
 * ver consumo são de Owner e Admin; enxergar a organização é de qualquer
 * membro. Marcas e execuções vivem na ProjectPolicy e na ExecutionPolicy.
 *
 * A Policy responde "este papel pode?". **Qual** membro o solicitante alcança
 * — o Admin remove só Members, o Owner nunca é alvo — e o piso do invariante
 * do Owner são decididos dentro da transação, no
 * `OrganizationMembershipService`, que relê os papéis sob lock.
 *
 * `users.is_admin` **nunca** aparece nesta classe. Staff da plataforma é o eixo
 * global do painel `/admin/*` e não concede papel algum em organização de
 * cliente; usar um eixo como atalho para o outro é falha de segurança
 * (SAD 20.7).
 */
class OrganizationPolicy
{
    /**
     * Enxergar a organização e seus saldos é capacidade de qualquer membro.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $user->belongsToOrganization($organization);
    }

    /**
     * Editar nome e slug — **[A DEFINIR]**, não ampliado além do Owner
     * (SAD 20.4).
     */
    public function update(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canUpdateOrganization() ?? false;
    }

    /**
     * Só a ability. A semântica da exclusão — soft delete, carência, efeito
     * sobre Marcas e assinatura — é **[A DEFINIR]** e não está implementada
     * (SAD 20.4).
     */
    public function delete(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canDeleteOrganization() ?? false;
    }

    /**
     * Cartão, plano e assinatura (SAD 20.4 e 12.18).
     */
    public function manageBilling(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canManageBilling() ?? false;
    }

    /**
     * Comprar créditos **para a organização** (SAD 12.13).
     *
     * Comprar créditos **pessoais** não passa por aqui: a carteira pessoal é do
     * usuário, acompanha-o entre organizações e não depende de papel algum
     * (SAD 12.2).
     */
    public function purchaseForOrganization(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canPurchaseForOrganization() ?? false;
    }

    /**
     * Convidar e remover membros, em geral.
     *
     * O papel do alvo é conferido no `OrganizationMembershipService`.
     */
    public function manageMembers(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canManageMembers() ?? false;
    }

    /**
     * Sair da organização: qualquer membro que não seja o Owner (SAD 20.4,
     * invariante 5).
     */
    public function leave(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canLeave() ?? false;
    }

    /**
     * Alterar papéis entre `admin` e `member`.
     */
    public function assignRoles(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canAssignRoles() ?? false;
    }

    public function transferOwnership(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canTransferOwnership() ?? false;
    }

    /**
     * Relatórios de consumo total. Ver os saldos é `view`.
     */
    public function viewCreditUsage(User $user, Organization $organization): bool
    {
        return $this->roleOf($user, $organization)?->canViewCreditUsage() ?? false;
    }

    /**
     * O papel na organização, ou `null` para quem não participa dela.
     */
    private function roleOf(User $user, Organization $organization): ?OrganizationRole
    {
        return $user->roleIn($organization);
    }
}
