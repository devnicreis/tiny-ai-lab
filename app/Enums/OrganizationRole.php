<?php

namespace App\Enums;

/**
 * Papel do usuário dentro de uma organização (SAD 20.4, ADR-010).
 *
 * Exatamente três valores existem — `owner`, `admin` e `member` —, espelhando
 * `organization_user.role` no banco. Um quarto papel exige nova ADR.
 *
 * Este Enum é a **única fonte da matriz de permissões**. Policies, Services e a
 * resposta da API perguntam aos métodos `can*()`; nenhum outro lugar compara
 * string de papel (SAD 18, Organizações).
 *
 * O papel `admin` daqui não tem relação nenhuma com `users.is_admin`, que é
 * staff da plataforma Arkion (SAD 20.7).
 */
enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Rótulo de interface do papel.
     *
     * Centralizado aqui para que uma troca de vocabulário — por exemplo,
     * "Creator" no lugar de "Member" — seja uma edição de uma linha, sem tocar
     * no valor persistido nem na matriz.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Member => 'Member',
        };
    }

    /**
     * O papel pode ser dado por convite ou por alteração de papel.
     *
     * `owner` nunca pode: a posse só muda de mãos por transferência, que
     * rebaixa o Owner anterior na mesma transação (SAD 20.4, invariante 3).
     */
    public function isAssignable(): bool
    {
        return $this !== self::Owner;
    }

    // ---------------------------------------------------------------------
    // Exclusivos do Owner (SAD 20.4)
    // ---------------------------------------------------------------------

    /**
     * Faturamento: cartão, plano e assinatura.
     */
    public function canManageBilling(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Comprar créditos **para a organização**.
     *
     * Créditos pessoais não passam por aqui: qualquer papel compra para a
     * própria carteira, que é do usuário (SAD 12.2).
     */
    public function canPurchaseForOrganization(): bool
    {
        return $this === self::Owner;
    }

    public function canDeleteOrganization(): bool
    {
        return $this === self::Owner;
    }

    public function canTransferOwnership(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Atribuir ou alterar papéis, limitado a `admin` ↔ `member`.
     *
     * Tornar alguém Owner não é alterar papel: é transferência de posse.
     */
    public function canAssignRoles(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Editar nome e slug da organização.
     *
     * Fora da matriz da SAD 20.4 — quem pode é **[A DEFINIR]**. Até a decisão,
     * a capacidade não é ampliada além do Owner.
     */
    public function canUpdateOrganization(): bool
    {
        return $this === self::Owner;
    }

    // ---------------------------------------------------------------------
    // Owner e Admin (SAD 20.4)
    // ---------------------------------------------------------------------

    /**
     * Convidar e remover membros, em geral.
     *
     * Responde se o papel gerencia membros **de algum tipo**; qual papel do
     * alvo ele alcança é `canManageMemberWithRole()`.
     */
    public function canManageMembers(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * Convidar ou remover um membro com o papel informado.
     *
     * O Owner alcança Admins e Members; o Admin, somente Members. Ninguém
     * alcança o Owner: ele não é convidado — só se torna Owner por
     * transferência — nem removido (SAD 20.4, invariantes 2 e 3).
     */
    public function canManageMemberWithRole(self $target): bool
    {
        return match ($this) {
            self::Owner => $target === self::Admin || $target === self::Member,
            self::Admin => $target === self::Member,
            self::Member => false,
        };
    }

    /**
     * Criar, editar e excluir Marcas e sub-brands, incluindo o Brand Core.
     */
    public function canManageBrands(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * Ver relatórios de consumo total de créditos.
     *
     * Ver os **saldos** é de todos os papéis e não depende deste método.
     */
    public function canViewCreditUsage(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * As capacidades do papel já resolvidas, para a interface.
     *
     * A UI nunca deduz o que um papel pode a partir do valor dele: recebe a
     * capacidade pronta do backend (SAD 12.12). Esconder um CTA é conveniência;
     * a recusa real continua sendo a Policy.
     *
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return [
            'manage_billing' => $this->canManageBilling(),
            'purchase_for_organization' => $this->canPurchaseForOrganization(),
            'delete_organization' => $this->canDeleteOrganization(),
            'transfer_ownership' => $this->canTransferOwnership(),
            'assign_roles' => $this->canAssignRoles(),
            'manage_members' => $this->canManageMembers(),
            'manage_brands' => $this->canManageBrands(),
            'view_credit_usage' => $this->canViewCreditUsage(),
        ];
    }
}
