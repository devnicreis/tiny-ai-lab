<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O usuário já participa da organização.
 *
 * Um papel por usuário em cada organização (SAD 4.15): mudar o papel de quem
 * já é membro é `changeRole()`, não uma segunda participação.
 */
class AlreadyOrganizationMemberException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'The user is already a member of this organization.',
        );
    }

    public function errorCode(): string
    {
        return 'already_organization_member';
    }

    public function status(): int
    {
        return Response::HTTP_CONFLICT;
    }
}
