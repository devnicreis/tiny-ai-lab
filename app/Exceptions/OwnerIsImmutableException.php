<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O Owner não pode ser removido, rebaixado nem sair (SAD 20.4, invariantes 2
 * e 5).
 *
 * Vale para qualquer solicitante, inclusive o próprio Owner: ele só deixa de
 * ser Owner por transferência de posse.
 */
class OwnerIsImmutableException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'The organization owner cannot be removed or have their role changed. Transfer ownership first.',
        );
    }

    public function errorCode(): string
    {
        return 'owner_is_immutable';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
