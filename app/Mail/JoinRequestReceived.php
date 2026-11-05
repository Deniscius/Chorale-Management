<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class JoinRequestReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $firstName,
        public readonly string $tenantName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.$this->tenantName.'] Demande d’adhésion reçue');
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.join-request-received');
    }
}
