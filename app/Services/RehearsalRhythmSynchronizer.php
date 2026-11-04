<?php

namespace App\Services;

use App\Enums\EventStatus;
use App\Enums\RehearsalType;
use App\Models\Rehearsal;
use App\Models\RehearsalRhythm;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class RehearsalRhythmSynchronizer
{
    public const GENERATED_NOTE = 'Répétition ordinaire programmée depuis le rythme de la chorale.';

    /**
     * Update only untouched, future, automatically generated rehearsals.
     * An edited/cancelled session, or one with attendance, works or reminders,
     * remains an explicit exception and must be handled individually.
     *
     * @param array<string, mixed>|null $previous previous rhythm attributes
     * @return array{updated:int,cancelled:int,protected:int,created:int}
     */
    public function synchronize(RehearsalRhythm $rhythm, ?array $previous): array
    {
        $counts = ['updated' => 0, 'cancelled' => 0, 'protected' => 0, 'created' => 0];
        if ($previous !== null) {
            DB::transaction(function () use ($rhythm, $previous, &$counts): void {
                $current = RehearsalRhythm::query()->whereKey($rhythm->id)->lockForUpdate()->firstOrFail();
                $sessions = Rehearsal::query()->where('rehearsal_rhythm_id', $current->id)
                    ->whereDate('date', '>=', today()->toDateString())
                    ->withExists(['attendances', 'reminders', 'works'])
                    ->orderBy('date')->lockForUpdate()->get();

                foreach ($sessions as $session) {
                    // Match the old rhythm precisely, so manual changes are not overwritten.
                    $untouched = $session->status === EventStatus::Planifiee
                        && ! $session->is_special
                        && $session->type === RehearsalType::Repetition
                        && $session->notes === self::GENERATED_NOTE
                        && (int) $session->choir_id === (int) $previous['choir_id']
                        && $session->starts_at === substr((string) $previous['starts_at'], 0, 5)
                        && $session->ends_at === (empty($previous['ends_at']) ? null : substr((string) $previous['ends_at'], 0, 5))
                        && $session->venue === ($previous['venue'] ?: null)
                        && ! $session->attendances_exists && ! $session->reminders_exists && ! $session->works_exists;

                    if (! $untouched) {
                        $counts['protected']++;

                        continue;
                    }

                    $date = CarbonImmutable::instance($session->date);
                    $stillScheduled = $current->is_active
                        && (int) $date->dayOfWeekIso === (int) $current->weekday
                        && $date->gte(CarbonImmutable::instance($current->effective_from)->startOfDay())
                        && (! $current->effective_until || $date->lte(CarbonImmutable::instance($current->effective_until)->startOfDay()));

                    if (! $stillScheduled) {
                        $session->update(['status' => EventStatus::Annulee->value]);
                        $counts['cancelled']++;

                        continue;
                    }

                    $session->update([
                        'choir_id' => $current->choir_id,
                        'starts_at' => $current->starts_at,
                        'ends_at' => $current->ends_at,
                        'venue' => $current->venue,
                    ]);
                    $counts['updated']++;
                }
            }, 3);
        }

        if ($rhythm->is_active) {
            $counts['created'] = app(RehearsalPlanner::class)->generate(
                CarbonImmutable::today(), CarbonImmutable::today()->addDays(45), $rhythm->choir_id
            );
        }

        return $counts;
    }
}
