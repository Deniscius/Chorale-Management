<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\MemberStatus;
use App\Enums\ReminderChannel;
use App\Enums\ReminderStatus;
use App\Jobs\SendReminder;
use App\Mail\RehearsalReminder;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Reminder;
use App\Notifications\RehearsalReminderNotification;
use App\Services\Sms\DeliveryRejected;
use App\Services\Sms\SmsSender;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ReminderDispatcher
{
    public function __construct(private readonly SmsSender $sms) {}

    public function rehearsalsToRemind(): Collection
    {
        return Rehearsal::with('works')->whereDate('date', today()->addDays(max(0, (int) config('chorale.reminders.days_before', 2))))->whereIn('status', ['planifiee', 'reportee'])->orderBy('date')->get();
    }

    public function dispatchAll(bool $dryRun = false): array
    {
        if (! $dryRun) {
            $this->recoverStale();
        }
        $total = ['rehearsals' => 0, 'sent' => 0, 'queued' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($this->rehearsalsToRemind() as $r) {
            $total['rehearsals']++;
            foreach ($this->dispatchForRehearsal($r, $dryRun) as $key => $n) {
                $total[$key] += $n;
            }
        }

        return $total;
    }

    public function dispatchForRehearsal(Rehearsal $rehearsal, bool $dryRun = false): array
    {
        if (! $dryRun) {
            $this->recoverStale();
        }
        $rehearsal->loadMissing('works');
        $result = ['sent' => 0, 'queued' => 0, 'skipped' => 0, 'failed' => 0];
        $members = Member::with('user')->where('status', MemberStatus::Actif->value)
            ->where(fn ($q) => $q->where('notify_email', true)->orWhere('notify_sms', true)->orWhereHas('user'))
            ->orderBy('id')->lazyById(200);
        foreach ($members as $member) {
            $channels = [];
            if ($member->canReceiveEmail()) {
                $channels[] = ReminderChannel::Mail;
            }
            if ($member->canReceiveSms()) {
                $channels[] = ReminderChannel::Sms;
            }
            if ($member->user) {
                $channels[] = ReminderChannel::Application;
            }
            foreach ($channels as $channel) {
                if ($dryRun) {
                    $result['skipped']++;

                    continue;
                }
                $recipient = match ($channel) {
                    ReminderChannel::Mail => $member->email,
                    ReminderChannel::Sms => $member->phone,
                    ReminderChannel::Application => (string) $member->user->id,
                };
                $outcome = $this->reserve($rehearsal, $member, $channel, (string) $recipient);
                $result[$outcome]++;
            }
        }

        return $result;
    }

    private function reserve(Rehearsal $r, Member $m, ReminderChannel $channel, string $recipient): string
    {
        $entry = DB::transaction(function () use ($r, $m, $channel, $recipient) {
            // firstOrCreate uses a savepoint on PostgreSQL when uniqueness races.
            $entry = Reminder::firstOrCreate(['rehearsal_id' => $r->id, 'member_id' => $m->id, 'channel' => $channel->value], ['recipient' => $recipient, 'subject' => $this->subject($r), 'body' => $this->body($r, $channel), 'status' => ReminderStatus::EnAttente, 'lease_token' => (string) Str::uuid(), 'reserved_at' => now()]);
            $created = $entry->wasRecentlyCreated;
            $entry = Reminder::query()->lockForUpdate()->findOrFail($entry->id);
            if (! $created && $entry->status !== ReminderStatus::Echec) {
                return null;
            }
            if (! $created) {
                $entry->update(['status' => ReminderStatus::EnAttente, 'recipient' => $recipient, 'subject' => $this->subject($r), 'body' => $this->body($r, $channel), 'error' => null, 'sent_at' => null, 'lease_token' => (string) Str::uuid(), 'reserved_at' => now(), 'sending_at' => null]);
            }

            return $entry;
        }, 3);
        if (! $entry) {
            return 'skipped';
        }

        return $this->schedule($entry);
    }

    private function schedule(Reminder $entry): string
    {
        if (config('chorale.reminders.queued', true)) {
            SendReminder::dispatch($entry->id, $entry->lease_token, $entry->channel->value)->afterCommit();

            return 'queued';
        }

        return $this->deliver($entry->id, $entry->lease_token);
    }

    public function deliver(int $id, string $token, bool $retry = false): string
    {
        $entry = DB::transaction(function () use ($id, $token, $retry) {
            $entry = Reminder::query()->lockForUpdate()->find($id);
            if (! $entry || $entry->lease_token !== $token || ! in_array($entry->status, $retry ? [ReminderStatus::EnAttente, ReminderStatus::Echec] : [ReminderStatus::EnAttente], true) || $entry->sending_at) {
                return null;
            }
            $entry->load(['member.user', 'rehearsal.works']);
            $allowed = config('chorale.reminders.enabled') && $entry->rehearsal?->status !== EventStatus::Annulee && $entry->member?->status === MemberStatus::Actif && match ($entry->channel) {
                ReminderChannel::Mail => $entry->member?->canReceiveEmail(),
                ReminderChannel::Sms => $entry->member?->canReceiveSms(),
                ReminderChannel::Application => $entry->member?->user !== null,
            };
            $recipient = match ($entry->channel) {
                ReminderChannel::Mail => $entry->member?->email,
                ReminderChannel::Sms => $entry->member?->phone,
                ReminderChannel::Application => (string) ($entry->member?->user?->id ?? ''),
            };
            if (! $allowed || ! $entry->rehearsal || $recipient !== $entry->recipient) {
                $entry->update(['status' => ReminderStatus::Ignore, 'error' => 'Destinataire ou préférences modifiés avant envoi.']);

                return null;
            }
            $entry->update(['status' => ReminderStatus::EnAttente, 'sending_at' => now(), 'attempts' => $entry->attempts + 1]);

            return $entry;
        }, 3);
        if (! $entry) {
            return 'skipped';
        }
        try {
            if ($entry->channel === ReminderChannel::Mail) {
                Mail::to($entry->recipient)->send(new RehearsalReminder($entry->rehearsal, $entry->member, $entry->body));
            } elseif ($entry->channel === ReminderChannel::Sms) {
                if (! $this->sms->send($entry->recipient, $entry->body)) {
                    throw new DeliveryRejected('Le fournisseur a refusé le SMS.');
                }
            } else {
                $entry->member->user->notify(new RehearsalReminderNotification($entry->rehearsal, $entry->member, $entry->body));
            }
        } catch (Throwable $e) {
            $this->finish($id, $token, ['status' => $e instanceof DeliveryRejected ? 'echec' : 'incertain', 'sending_at' => null, 'error' => mb_substr($e->getMessage(), 0, 2000)]);

            return 'failed';
        }
        // Persist success outside the transport catch. A SQL failure after an
        // accepted send must not become a retryable delivery failure.
        $this->finish($id, $token, ['status' => 'envoye', 'sent_at' => now(), 'error' => null]);

        return 'sent';
    }

    private function finish(int $id, string $token, array $data): void
    {
        DB::transaction(function () use ($id, $token, $data) {
            $entry = Reminder::query()->lockForUpdate()->find($id);
            if ($entry && $entry->lease_token === $token && $entry->status === ReminderStatus::EnAttente) {
                $entry->update($data);
            }
        }, 3);
    }

    public function recoverStale(): int
    {
        $cutoff = now()->subMinutes(max(5, (int) config('chorale.reminders.lease_minutes', 60)));

        // An expired reservation may already have reached the provider: never blindly resend.
        return Reminder::where('status', 'en_attente')->where(function ($q) use ($cutoff) {
            $q->where('reserved_at', '<', $cutoff)->orWhere(fn ($q) => $q->whereNull('reserved_at')->where('created_at', '<', $cutoff));
        })->update(['status' => 'incertain', 'error' => 'Envoi interrompu ou réservation expirée. Vérifiez le fournisseur avant de décider une relance.']);
    }

    public function resolve(int $id, string $decision): void
    {
        if (! in_array($decision, ['sent', 'retry', 'ignore'], true)) {
            throw ValidationException::withMessages(['operation' => 'Décision inconnue.']);
        }
        $entry = DB::transaction(function () use ($id, $decision) {
            $entry = Reminder::query()->lockForUpdate()->findOrFail($id);
            if ($entry->status !== ReminderStatus::Incertain) {
                throw ValidationException::withMessages(['operation' => 'Seuls les envois à vérifier peuvent être rapprochés.']);
            }
            $entry->update(['status' => match ($decision) {
                'sent' => ReminderStatus::Envoye,'ignore' => ReminderStatus::Ignore,default => ReminderStatus::EnAttente
            }, 'sent_at' => $decision === 'sent' ? now() : null, 'error' => null, 'sending_at' => null, 'lease_token' => (string) Str::uuid(), 'reserved_at' => now()]);

            return $entry;
        }, 3);
        if ($decision === 'retry') {
            $this->schedule($entry);
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
