<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O convite foi revogado por quem gerencia os membros da organização.
 */
class InvitationRevokedException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'This invitation has been revoked.',
        );
    }

    public function errorCode(): string
    {
        return 'invitation_revoked';
    }

    public function status(): int
    {
        return Response::HTTP_GONE;
    }
}
