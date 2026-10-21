<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O usuário autenticado ainda não verificou o e-mail no Firebase.
 *
 * Sem a claim `email_verified = true`, qualquer pessoa poderia se cadastrar
 * com o e-mail alheio e sequestrar o convite.
 */
class InvitationEmailNotVerifiedException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'Verify your email address before accepting this invitation.',
        );
    }

    public function errorCode(): string
    {
        return 'email_not_verified';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
