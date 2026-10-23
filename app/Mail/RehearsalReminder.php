<?php

namespace App\Mail;

use App\Models\Member;
use App\Models\Rehearsal;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Rappel de répétition envoyé à un choriste.
 *
 * Le message est produit à partir des données de la séance : date, horaire,
 * lieu et programme d'œuvres. Aucune donnée personnelle autre que le prénom
 * du destinataire n'est utilisée.
 */
class RehearsalReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Rehearsal $rehearsal,
        public readonly ?Member $member = null,
        public readonly string $body = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Rappel : répétition du '
                .$this->rehearsal->date?->translatedFormat('l j F')
                .' à '.($this->rehearsal->starts_at ?: '—'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.rehearsal-reminder',
            with: [
                'rehearsal' => $this->rehearsal,
                'member' => $this->member,
                'body' => $this->body,
            ],
        );
    }
}
