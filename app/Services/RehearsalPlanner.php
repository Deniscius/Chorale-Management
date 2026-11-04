<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\RehearsalType;
use App\Models\Rehearsal;
use App\Models\RehearsalRhythm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RehearsalPlanner
{
    /**
     * Plan regular rehearsals from an explicit weekly schedule.
     *
     * Existing sessions are never edited: this protects attendance records,
     * overrides, cancellations, and already delivered reminders.
     */
    public function generate(CarbonImmutable $from, CarbonImmutable $until, ?int $choirId = null, ?int $rhythmId = null): int
    {
        if ($until->lt($from) || $until->diffInDays($from) > 366) {
            throw new \InvalidArgumentException('La période doit être croissante et limitée à 366 jours.');
        }

        $created = 0;
        $rhythms = RehearsalRhythm::query()->where('is_active', true)
            ->when($rhythmId !== null, fn ($q) => $q->whereKey($rhythmId))
            ->when($choirId !== null, fn ($q) => $q->where('choir_id', $choirId))
            ->whereDate('effective_from', '<=', $until->toDateString())
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $from->toDateString()))
            ->orderBy('id')->get();

        foreach ($rhythms as $rhythm) {
            $start = $from->max(CarbonImmutable::instance($rhythm->effective_from));
            $end = $rhythm->effective_until
                ? $until->min(CarbonImmutable::instance($rhythm->effective_until))
                : $until;
            for ($day = $start->startOfDay(); $day->lte($end); $day = $day->addDay()) {
                if ($day->dayOfWeekIso !== $rhythm->weekday
                    || ($day->isToday() && substr($rhythm->starts_at, 0, 5) < now()->format('H:i'))) {
                    continue;
                }
                DB::transaction(function () use ($rhythm, $day, &$created): void {
                    // Serialize same-tenant generation on this schedule, including
                    // concurrent scheduler invocations.
                    $lockedRhythm = RehearsalRhythm::query()->whereKey($rhythm->id)->lockForUpdate()->first();
                    if (! $lockedRhythm || ! $lockedRhythm->is_active) {
                        return;
                    }
                    $date = $day->toDateString();
                    // Manual or special rehearsals at a different time may coexist.
                    $exists = Rehearsal::query()->where('choir_id', $rhythm->choir_id)
                        ->whereDate('date', $date)
                        ->where('starts_at', substr($rhythm->starts_at, 0, 5).':00')
                        ->exists();
                    if ($exists) {
                        return;
                    }
                    // Only restore sessions previously cancelled by automated rhythm
                    // synchronization. A manually cancelled session is never restored.
                    $recoverable = Rehearsal::query()
                        ->where('rehearsal_rhythm_id', $rhythm->id)
                        ->whereDate('date', $date)
                        ->where('status', EventStatus::Annulee->value)
                        ->where('notes', RehearsalRhythmSynchronizer::RESCHEDULED_NOTE)
                        ->withExists(['attendances', 'reminders', 'works'])
                        ->lockForUpdate()->first();
                    if ($recoverable && ! $recoverable->attendances_exists
                        && ! $recoverable->reminders_exists && ! $recoverable->works_exists) {
                        $recoverable->update([
                            'status' => EventStatus::Planifiee->value,
                            'starts_at' => $rhythm->starts_at,
                            'ends_at' => $rhythm->ends_at,
                            'venue' => $rhythm->venue,
                            'notes' => RehearsalRhythmSynchronizer::GENERATED_NOTE,
                        ]);
                        return;
                    }
                    Rehearsal::create([
                        'choir_id' => $rhythm->choir_id,
                        'rehearsal_rhythm_id' => $rhythm->id,
                        'date' => $date,
                        'starts_at' => $rhythm->starts_at,
                        'ends_at' => $rhythm->ends_at,
                        'venue' => $rhythm->venue,
                        'type' => RehearsalType::Repetition->value,
                        'status' => EventStatus::Planifiee->value,
                        'is_special' => false,
                        'notes' => 'Répétition ordinaire programmée depuis le rythme de la chorale.',
                    ]);
                    $created++;
                }, 3);
            }
        }

        return $created;
    }
}
