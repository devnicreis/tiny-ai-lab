<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O papel do solicitante não permite a operação (SAD 20.4).
 *
 * Admin removendo Admin, Admin atribuindo papéis, Member gerenciando membros,
 * quem não é Owner transferindo a posse. O papel é relido dentro da transação,
 * e é essa leitura — não a Policy — que dispara esta exception.
 */
class InsufficientOrganizationRoleException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'Your role in this organization does not allow this action.',
        );
    }

    public function errorCode(): string
    {
        return 'insufficient_organization_role';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
