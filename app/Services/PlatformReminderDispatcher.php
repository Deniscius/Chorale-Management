<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\AttendanceIncident;
use App\Models\Contribution;
use App\Models\Correspondence;
use App\Models\InstrumentMaintenance;
use App\Models\InstrumentMovement;
use App\Models\NotificationDispatch;
use App\Models\Performance;
use App\Models\Rehearsal;
use App\Models\User;
use App\Notifications\PlatformReminderNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Rappels applicatifs transversaux. Chaque envoi est journalisé par une
 * empreinte stable afin que les exécutions quotidiennes restent idempotentes.
 */
final class PlatformReminderDispatcher
{
    /** @return array{candidates:int,sent:int,skipped:int,failed:int} */
    public function dispatchAll(bool $dryRun = false): array
    {
        $result = ['candidates' => 0, 'sent' => 0, 'skipped' => 0, 'failed' => 0];
        $users = User::query()->with('member')->get();
        $members = $users->filter(fn (User $user) => $user->member?->status === MemberStatus::Actif)->values();

        $this->dispatchCalendar($members, $dryRun, $result);
        $this->dispatchPersonalObligations($dryRun, $result);
        $this->dispatchOperationalDeadlines($users, $dryRun, $result);

        return $result;
    }

    private function dispatchCalendar(Collection $members, bool $dryRun, array &$result): void
    {
        foreach ($this->leadDays() as $days) {
            $date = today()->addDays($days);

            // Le rappel de répétition configuré (J-2 par défaut) conserve ses canaux
            // e-mail/SMS. Les rappels complémentaires J-7 et jour J passent ici.
            if ($days !== max(0, (int) config('chorale.reminders.days_before', 2))) {
                Rehearsal::query()->whereDate('date', $date)->whereIn('status', ['planifiee', 'reportee'])
                    ->each(function (Rehearsal $event) use ($members, $days, $dryRun, &$result) {
                        $this->sendTo($members, [
                            'key' => "rehearsal:{$event->id}:{$event->date->toDateString()}:d{$days}",
                            'kind' => 'rehearsal', 'subject' => $event, 'date' => $event->date,
                            'title' => $days === 0 ? 'Répétition aujourd’hui' : 'Répétition à venir',
                            'message' => $this->whenMessage('la répétition', $event->date, $days, $event->starts_at, $event->venue),
                            'url' => route('personal-notebook.show', absolute: false), 'icon' => 'music',
                        ], $dryRun, $result);
                    });
            }

            Performance::query()->whereDate('scheduled_at', $date)->whereIn('status', ['planifiee', 'reportee'])
                ->each(function (Performance $event) use ($members, $days, $dryRun, &$result) {
                    $this->sendTo($members, [
                        'key' => "performance:{$event->id}:{$event->scheduled_at->toDateString()}:d{$days}",
                        'kind' => 'performance', 'subject' => $event, 'date' => $event->scheduled_at,
                        'title' => $days === 0 ? 'Événement aujourd’hui' : 'Événement à venir',
                        'message' => $this->whenMessage($event->title, $event->scheduled_at, $days, $event->scheduled_at->format('H:i'), $event->venue),
                        'url' => route('personal-notebook.show', absolute: false), 'icon' => 'sparkles',
                    ], $dryRun, $result);
                });

            Activity::query()->whereDate('starts_at', $date)->whereIn('status', ['planifiee', 'reportee'])
                ->with('participantRecords.member.user')->each(function (Activity $event) use ($members, $days, $dryRun, &$result) {
                    $participants = $event->participantRecords->isEmpty()
                        ? $members
                        : $event->participantRecords->map(fn ($record) => $record->member?->user)->filter()->unique('id')->values();
                    $this->sendTo($participants, [
                        'key' => "activity:{$event->id}:{$event->starts_at->toDateString()}:d{$days}",
                        'kind' => 'activity', 'subject' => $event, 'date' => $event->starts_at,
                        'title' => $days === 0 ? 'Activité aujourd’hui' : 'Activité à venir',
                        'message' => $this->whenMessage($event->title, $event->starts_at, $days, $event->starts_at->format('H:i'), $event->venue),
                        'url' => route('personal-notebook.show', absolute: false), 'icon' => 'calendar',
                    ], $dryRun, $result);
                });
        }
    }

    private function dispatchPersonalObligations(bool $dryRun, array &$result): void
    {
        Contribution::query()->with('member.user')
            ->whereIn('status', ['due', 'partiel', 'retard'])
            ->where(fn ($query) => $query->where('year', '<', today()->year)
                ->orWhere(fn ($current) => $current->where('year', today()->year)->where('month', '<=', today()->month)))
            ->each(function (Contribution $item) use ($dryRun, &$result) {
                if (! $item->member?->user || $item->balance() <= 0) {
                    return;
                }
                $this->sendTo(collect([$item->member->user]), [
                    'key' => "contribution:{$item->id}:week:".today()->format('o-W'),
                    'kind' => 'contribution', 'subject' => $item, 'date' => today(),
                    'title' => 'Cotisation à régulariser',
                    'message' => 'votre cotisation de '.$item->monthLabel().' '.$item->year.' présente un reste de '.number_format($item->balance(), 0, ',', ' ').' FCFA.',
                    'url' => route('personal-notebook.show', absolute: false), 'icon' => 'banknotes',
                ], $dryRun, $result);
            });

        AttendanceIncident::query()->with('member.user')->where('status', AttendanceIncident::PENDING)
            ->each(function (AttendanceIncident $item) use ($dryRun, &$result) {
                if (! $item->member?->user) {
                    return;
                }
                $detail = $item->incident_type === 'late' ? 'un retard de '.$item->minutes_late.' minutes' : 'une absence';
                $this->sendTo(collect([$item->member->user]), [
                    'key' => "attendance-incident:{$item->id}:pending",
                    'kind' => 'attendance', 'subject' => $item, 'date' => $item->occurred_on,
                    'title' => 'Suivi de votre assiduité',
                    'message' => $detail.' a été enregistré pour « '.$item->event_label.' » le '.$item->occurred_on->format('d/m/Y').'.',
                    'url' => route('personal-notebook.show', absolute: false), 'icon' => 'clock',
                ], $dryRun, $result);
            });
    }

    private function dispatchOperationalDeadlines(Collection $users, bool $dryRun, array &$result): void
    {
        foreach ($this->leadDays() as $days) {
            $date = today()->addDays($days);
            InstrumentMaintenance::query()->with('instrument')->whereDate('scheduled_for', $date)->where('status', 'planifiee')
                ->each(function (InstrumentMaintenance $item) use ($users, $days, $date, $dryRun, &$result) {
                    $this->sendTo($this->moduleUsers($users, UserRole::MODULE_INSTRUMENTS), [
                        'key' => "maintenance:{$item->id}:{$date->toDateString()}:d{$days}",
                        'kind' => 'maintenance', 'subject' => $item, 'date' => $date,
                        'title' => 'Maintenance d’instrument',
                        'message' => $this->whenMessage('la maintenance de '.$item->instrument->name, $date, $days),
                        'url' => route('instruments.maintenances', absolute: false), 'icon' => 'wrench',
                    ], $dryRun, $result);
                });

            InstrumentMovement::query()->with('instrument', 'responsible.user')
                ->whereNull('actual_return_on')->whereDate('expected_return_on', $date)
                ->each(function (InstrumentMovement $item) use ($users, $days, $date, $dryRun, &$result) {
                    $recipients = collect([$item->responsible?->user])->filter();
                    if ($recipients->isEmpty()) {
                        $recipients = $this->moduleUsers($users, UserRole::MODULE_INSTRUMENTS);
                    }
                    $this->sendTo($recipients, [
                        'key' => "instrument-return:{$item->id}:{$date->toDateString()}:d{$days}",
                        'kind' => 'instrument-return', 'subject' => $item, 'date' => $date,
                        'title' => 'Retour d’instrument',
                        'message' => $this->whenMessage('le retour de '.$item->instrument->name, $date, $days),
                        'url' => route('personal-notebook.show', absolute: false), 'icon' => 'cube',
                    ], $dryRun, $result);
                });
        }

        Correspondence::query()->with('assignedMember.user')->open()->whereNotNull('assigned_to')
            ->each(function (Correspondence $item) use ($dryRun, &$result) {
                if (! $item->assignedMember?->user) {
                    return;
                }
                $this->sendTo(collect([$item->assignedMember->user]), [
                    'key' => "correspondence:{$item->id}:assigned:{$item->assigned_to}",
                    'kind' => 'correspondence', 'subject' => $item, 'date' => $item->correspondence_date,
                    'title' => 'Courrier à traiter',
                    'message' => 'le courrier « '.$item->subject.' » vous a été confié et reste à traiter.',
                    'url' => route('correspondences.index', absolute: false), 'icon' => 'mail',
                ], $dryRun, $result);
            });
    }

    private function sendTo(Collection $users, array $item, bool $dryRun, array &$result): void
    {
        foreach ($users->filter()->unique('id') as $user) {
            $result['candidates']++;
            $fingerprint = hash('sha256', $item['key']);
            if ($dryRun) {
                $result['skipped']++;

                continue;
            }

            $dispatch = $this->reserveDispatch($user, $fingerprint, $item);
            if (! $dispatch) {
                $result['skipped']++;

                continue;
            }

            try {
                $user->notify(new PlatformReminderNotification(
                    $item['title'], $item['message'], $item['url'], $item['kind'], $fingerprint, $item['icon'],
                ));
                $dispatch->update(['notified_at' => now(), 'error' => null]);
                $result['sent']++;
            } catch (Throwable $exception) {
                $dispatch->update(['error' => mb_substr($exception->getMessage(), 0, 2000)]);
                report($exception);
                $result['failed']++;
            }
        }
    }

    private function reserveDispatch(User $user, string $fingerprint, array $item): ?NotificationDispatch
    {
        $seed = NotificationDispatch::query()->firstOrCreate(
            ['user_id' => $user->id, 'fingerprint' => $fingerprint],
            ['kind' => $item['kind'], 'subject_type' => $item['subject']::class, 'subject_id' => $item['subject']->getKey(), 'scheduled_for' => $item['date']],
        );

        return DB::transaction(function () use ($seed) {
            $dispatch = NotificationDispatch::query()->lockForUpdate()->findOrFail($seed->id);
            if ($dispatch->notified_at || ($dispatch->attempts > 0 && blank($dispatch->error)) || $dispatch->attempts >= 3) {
                return null;
            }
            $dispatch->update(['attempts' => $dispatch->attempts + 1, 'error' => null]);

            return $dispatch;
        }, 3);
    }

    private function leadDays(): array
    {
        $days = array_map('intval', (array) config('chorale.reminders.platform_days', [7, 2, 0]));

        return array_values(array_unique(array_filter($days, fn (int $day) => $day >= 0 && $day <= 30)));
    }

    private function moduleUsers(Collection $users, string $module): Collection
    {
        return $users->filter(fn (User $user) => $user->canAccessModule($module))->values();
    }

    private function whenMessage(string $label, mixed $date, int $days, ?string $time = null, ?string $venue = null): string
    {
        $when = $days === 0 ? 'a lieu aujourd’hui' : 'a lieu dans '.$days.' jour'.($days > 1 ? 's' : '').', le '.$date->translatedFormat('l j F');

        return $label.' '.$when.($time ? ' à '.$time : '').($venue ? ' — '.$venue : '').'.';
    }
}
