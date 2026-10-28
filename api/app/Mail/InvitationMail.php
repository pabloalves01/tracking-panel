<?php

namespace App\Mail;

use App\Support\Membership;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $organization,
        public string $url,
        public string $expiresAt,
        public string $role = Membership::MEMBER,
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
            with: [
                'roleLabel' => $this->roleLabel(),
                'roleSummary' => $this->roleSummary(),
            ],
        );
    }

    private function roleLabel(): string
    {
        return match ($this->role) {
            Membership::OWNER => 'Proprietário',
            Membership::ADMIN => 'Administrador',
            default => 'Membro',
        };
    }

    /**
     * Uma linha dizendo o que o papel permite. Quem recebe um convite
     * raramente sabe o que "admin" significa naquele sistema.
     */
    private function roleSummary(): string
    {
        return match ($this->role) {
            Membership::OWNER => 'Acesso total, incluindo gerenciar usuários e convites.',
            Membership::ADMIN => 'Gerencia usuários e convites, além de todos os cadastros.',
            default => 'Acessa e mantém os cadastros e as operações do dia a dia.',
        };
    }
}
