<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O convite já foi aceito e não pode ser usado de novo.
 *
 * Um convite é de uso único: aceitá-lo outra vez só é idempotente para quem
 * continua membro da organização.
 */
class InvitationAlreadyAcceptedException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'This invitation has already been accepted.',
        );
    }

    public function errorCode(): string
    {
        return 'invitation_already_accepted';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
