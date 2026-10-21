<?php

namespace App\Mail;

use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Convite para participar de uma organização, enviado ao e-mail convidado.
 *
 * Texto ao cliente final em inglês (CLAUDE.md regra 25). Enfileirado em
 * `notifications`. O provedor SMTP ainda é **[A DEFINIR]**: o envio é
 * conveniência, nunca o único caminho — o link também volta a quem convidou.
 *
 * Recebe apenas valores prontos, nunca o Model do convite: o token em claro
 * não existe no banco, só no link.
 */
class OrganizationInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $organizationName,
        public readonly string $inviterName,
        public readonly string $roleLabel,
        public readonly string $acceptUrl,
        public readonly CarbonInterface $expiresAt,
    ) {
        $this->onQueue('notifications');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to join {$this->organizationName} on Arkion",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.organization-invitation',
        );
    }
}
