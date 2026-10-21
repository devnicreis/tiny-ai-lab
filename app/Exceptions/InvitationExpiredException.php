<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O convite passou de `expires_at` sem ser aceito.
 *
 * Quem convidou precisa emitir um novo convite.
 */
class InvitationExpiredException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'This invitation has expired.',
        );
    }

    public function errorCode(): string
    {
        return 'invitation_expired';
    }

    public function status(): int
    {
        return Response::HTTP_GONE;
    }
}
