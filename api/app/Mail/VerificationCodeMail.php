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
 * O payload carrega o código de seis dígitos, então vai cifrado no storage da fila.
 */
class VerificationCodeMail extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public function __construct(public string $code, public int $minutes = 10) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Seu código Logiv',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.verification-code',
            text: 'mail.verification-code-text',
        );
    }
}
