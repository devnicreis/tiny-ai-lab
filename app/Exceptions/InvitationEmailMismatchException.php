<?php

namespace App\Exceptions;

use App\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

/**
 * O e-mail do usuário autenticado não é o e-mail para o qual o convite foi
 * emitido.
 *
 * O link do convite não é credencial suficiente: quem o aceita precisa provar,
 * pelo Firebase, que é o dono do e-mail convidado.
 */
class InvitationEmailMismatchException extends OrganizationMembershipException
{
    public static function for(Organization $organization): self
    {
        return new self(
            self::idOf($organization),
            'This invitation was sent to a different email address.',
        );
    }

    public function errorCode(): string
    {
        return 'invitation_email_mismatch';
    }

    public function status(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
