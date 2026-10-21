<?php

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

/**
 * Nenhum convite corresponde ao token informado.
 *
 * Token inexistente e token adulterado são indistinguíveis — o banco guarda
 * apenas o hash —, e a resposta não revela a qual organização um token
 * pertenceria.
 */
class InvitationNotFoundException extends OrganizationMembershipException
{
    public static function make(): self
    {
        return new self(null, 'This invitation does not exist.');
    }

    public function errorCode(): string
    {
        return 'invitation_not_found';
    }

    public function status(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
