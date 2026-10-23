<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Mail\RehearsalReminder;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Reminder;
use App\Services\Sms\SmsSender;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Envoi des rappels de répétition.
 *
 * Le service sépare trois responsabilités :
 *
 *  - la sélection des répétitions à rappeler (délai configurable) ;
 *  - la construction du message, identique pour tous les canaux ;
 *  - l'envoi proprement dit, journalisé dans la table `reminders`.
 *
 * La journalisation préalable garantit l'idempotence : si la commande est
 * relancée le même jour, aucun choriste ne reçoit deux fois le même rappel.
 */
class ReminderDispatcher
{
    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Répétitions planifiées devant faire l'objet d'un rappel.
     *
     * @return Collection<int, Rehearsal>
     */
    public function rehearsalsToRemind(): Collection
    {
        $days = max(0, (int) config('chorale.reminders.days_before', 2));

        return Rehearsal::query()
            ->whereDate('date', today()->addDays($days))
            ->whereIn('status', ['planifiee', 'reportee'])
            ->orderBy('date')
            ->get();
    }

    /**
     * Envoie les rappels de toutes les répétitions concernées.
     *
     * @return array{rehearsals: int, sent: int, skipped: int, failed: int}
     */
    public function dispatchAll(bool $dryRun = false): array
    {
        $rehearsals = $this->rehearsalsToRemind();
        $totals = ['rehearsals' => $rehearsals->count(), 'sent' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($rehearsals as $rehearsal) {
            $result = $this->dispatchForRehearsal($rehearsal, $dryRun);

            $totals['sent'] += $result['sent'];
            $totals['skipped'] += $result['skipped'];
            $totals['failed'] += $result['failed'];
        }

        return $totals;
    }

    /**
     * Envoie les rappels d'une répétition à tous les choristes concernés.
     *
     * @return array{sent: int, skipped: int, failed: int}
     */
    public function dispatchForRehearsal(Rehearsal $rehearsal, bool $dryRun = false): array
    {
        $result = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        $members = Member::query()
            ->where('status', MemberStatus::Actif->value)
            ->where(function ($query) {
                $query->where('notify_email', true)->orWhere('notify_sms', true);
            })
            ->orderBy('last_name')
            ->get();

        foreach ($members as $member) {
            foreach ($this->channelsFor($member) as $channel) {
                $recipient = $channel === ReminderChannel::Mail ? $member->email : $member->phone;

                $outcome = $this->send($rehearsal, $member, $channel, (string) $recipient, $dryRun);

                $result[$outcome]++;
            }
        }

        return $result;
    }

    /**
     * Canaux réellement disponibles pour un choriste.
     *
     * @return array<int, ReminderChannel>
     */
    private function channelsFor(Member $member): array
    {
        $channels = [];

        if ($member->canReceiveEmail()) {
            $channels[] = ReminderChannel::Mail;
        }

        if ($member->canReceiveSms()) {
            $channels[] = ReminderChannel::Sms;
        }

        return $channels;
    }

    /**
     * Envoie un rappel sur un canal et journalise le résultat.
     *
     * @return 'sent'|'skipped'|'failed'
     */
    private function send(
        Rehearsal $rehearsal,
        Member $member,
        ReminderChannel $channel,
        string $recipient,
        bool $dryRun,
    ): string {
        // Idempotence : le rappel a-t-il déjà été traité ?
        $alreadySent = Reminder::query()
            ->where('rehearsal_id', $rehearsal->getKey())
            ->where('member_id', $member->getKey())
            ->where('channel', $channel->value)
            ->whereIn('status', [ReminderStatus::Envoye->value, ReminderStatus::EnAttente->value])
            ->exists();

        if ($alreadySent) {
            return 'skipped';
        }

        $subject = $this->subject($rehearsal);
        $body = $this->body($rehearsal, $channel);

        if ($dryRun) {
            return 'skipped';
        }

        try {
            $reminder = Reminder::create([
                'rehearsal_id' => $rehearsal->getKey(),
                'member_id' => $member->getKey(),
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'status' => ReminderStatus::EnAttente,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Une autre exécution concurrente a déjà pris ce rappel en charge.
            return 'skipped';
        }

        try {
            if ($channel === ReminderChannel::Mail) {
                Mail::to($recipient)->send(new RehearsalReminder($rehearsal, $member, $body));
            } else {
                $this->sms->send($recipient, $body);
            }

            $reminder->update([
                'status' => ReminderStatus::Envoye,
                'sent_at' => now(),
            ]);

            return 'sent';
        } catch (Throwable $exception) {
            $reminder->update([
                'status' => ReminderStatus::Echec,
                'error' => $exception->getMessage(),
            ]);

            return 'failed';
        }
    }

    public function subject(Rehearsal $rehearsal): string
    {
        return 'Rappel : répétition du '.$rehearsal->date?->translatedFormat('l j F').' à '.($rehearsal->starts_at ?: '—');
    }

    /**
     * Corps du message. Le SMS est volontairement plus court (160 caractères).
     */
    public function body(Rehearsal $rehearsal, ReminderChannel $channel): string
    {
        $when = $rehearsal->date?->translatedFormat('l j F Y');
        $time = $rehearsal->timeRange();
        $venue = $rehearsal->venue;

        $parts = ['Rappel de la répétition du '.$when];

        if ($time) {
            $parts[] = 'de '.$time;
        }

        if ($venue) {
            $parts[] = 'à '.$venue;
        }

        if ($channel === ReminderChannel::Sms) {
            return mb_substr(implode(' ', $parts).'. Merci de votre présence.', 0, 160);
        }

        $body = implode(' ', $parts).".\n\n";

        if ($rehearsal->works->isNotEmpty()) {
            $body .= "Œuvres au programme :\n";
            $body .= $rehearsal->works->map(fn ($work) => '- '.$work->title)->implode("\n");
            $body .= "\n\n";
        }

        if ($rehearsal->notes) {
            $body .= $rehearsal->notes."\n\n";
        }

        return $body.'Merci de votre présence et de votre ponctualité.';
    }
}
