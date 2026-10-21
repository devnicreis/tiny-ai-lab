<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O papel `owner` foi pedido fora da transferência de posse (SAD 20.4).
 *
 * Ninguém é convidado como Owner nem promovido a Owner por "alterar papel":
 * a posse só muda de mãos pela transferência, que rebaixa o Owner anterior na
 * mesma transação.
 */
class OwnerRoleRequiresTransferException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'The owner role can only be granted by transferring ownership.',
        );
    }

    public function errorCode(): string
    {
        return 'owner_role_requires_transfer';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
