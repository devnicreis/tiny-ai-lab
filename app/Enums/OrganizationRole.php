<?php

namespace App\Enums;

/**
 * Papel do usuário dentro de uma organização (SAD 20.4).
 *
 * Exatamente dois valores existem — `admin` e `member`, espelhando
 * `organization_user.role` no banco. Não há um terceiro papel (`owner`): a
 * matriz de permissões da SAD 20.4 não distingue nenhuma capacidade adicional
 * para um "dono", e introduzir um terceiro papel exige ADR.
 */
enum OrganizationRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Gerenciar faturamento da organização — apenas Admin (SAD 20.4).
     */
    public function canManageBilling(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Gerenciar membros — apenas Admin (SAD 20.4).
     */
    public function canManageMembers(): bool
    {
        return $this === self::Admin;
    }

    /**
     * Comprar créditos para a organização — apenas Admin (SAD 20.4).
     */
    public function canPurchaseForOrganization(): bool
    {
        return $this === self::Admin;
    }
}
