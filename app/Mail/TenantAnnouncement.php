<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** A deliberate informational message sent by a platform or choir administrator. */
final class TenantAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $subjectLine,
        public readonly string $messageBody,
        public readonly string $tenantName,
        public readonly ?string $actionUrl = null,
        public readonly ?string $actionLabel = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '['.$this->tenantName.'] '.$this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.tenant-announcement');
    }
}
