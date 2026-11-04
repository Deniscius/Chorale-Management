<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
        return new Content(markdown: 'emails.tenant-announcement', with: ['actionLink' => $this->actionLink()]);
    }

    /** Always render an absolute HTTPS application link in an e-mail client. */
    public function actionLink(): ?string
    {
        if (blank($this->actionUrl)) {
            return null;
        }

        return Str::startsWith($this->actionUrl, ['https://', 'http://'])
            ? $this->actionUrl
            : url($this->actionUrl);
    }
}
