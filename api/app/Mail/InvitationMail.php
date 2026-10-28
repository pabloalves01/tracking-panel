<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * O payload carrega o token do convite na url, então vai cifrado no storage da fila.
 */
class InvitationMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $organization,
        public string $url,
        public string $expiresAt,
        public ?string $invitedBy = null,
        public bool $accountExists = false,
        public string $organizationKind = 'empresa',
    ) {}

    public function envelope(): Envelope
    {
        // O assunto diz quem convidou e para onde: é o que decide se o
        // e-mail é aberto, e o que separa o convite de um disparo genérico.
        return new Envelope(
            subject: sprintf('%s convidou você para a %s no LogIV', $this->invitedBy ?: $this->organization, $this->organization),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.invitation',
            text: 'mail.invitation-text',
        );
    }
}
