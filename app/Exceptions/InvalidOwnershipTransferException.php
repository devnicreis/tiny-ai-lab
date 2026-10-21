<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Transferência de posse para o próprio Owner.
 *
 * O destino precisa ser **outro** membro existente (SAD 20.4, invariante 3);
 * transferir para si mesmo rebaixaria e promoveria a mesma participação.
 */
class InvalidOwnershipTransferException extends OrganizationMembershipException
{
    public static function toSelf(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'Ownership must be transferred to another member of the organization.',
        );
    }

    public function errorCode(): string
    {
        return 'invalid_ownership_transfer';
    }

    public function status(): int
    {
        return Response::HTTP_UNPROCESSABLE_ENTITY;
    }
}
