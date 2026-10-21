<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * Já existe um convite pendente para este e-mail nesta organização.
 *
 * Um convite pendente por e-mail em cada organização, garantido também pelo
 * índice único parcial de `organization_invitations`. Para reenviar, revogue o
 * convite anterior.
 */
class PendingInvitationExistsException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'There is already a pending invitation for this email address.',
        );
    }

    public function errorCode(): string
    {
        return 'pending_invitation_exists';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
