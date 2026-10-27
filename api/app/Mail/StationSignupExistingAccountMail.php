<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StationSignupExistingAccountMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Entre para cadastrar o posto',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.station-signup-existing-account',
            text: 'mail.station-signup-existing-account-text',
        );
    }
}
