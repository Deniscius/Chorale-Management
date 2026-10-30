<?php

namespace App\Services;

use App\Enums\ContributionFrequency;
use App\Enums\ContributionStatus;
use App\Enums\MemberStatus;
use App\Models\Contribution;
use App\Models\FinancialType;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ContributionSchedule
{
    /**
     * Échéances arrivées à terme pour un choriste et un type récurrent.
     *
     * @return Collection<int, array{period:CarbonImmutable,due_on:CarbonImmutable}>
     */
    public function duePeriods(Member $member, FinancialType $type, ?CarbonImmutable $asOf = null): Collection
    {
        $asOf ??= CarbonImmutable::today();
        $frequency = ContributionFrequency::tryFrom((int) $type->frequency_months);
        if (! $frequency || $frequency === ContributionFrequency::Ponctuelle || ! $member->joined_on) {
            return collect();
        }

        $joined = CarbonImmutable::instance($member->joined_on)->startOfDay();
        $anchor = $type->starts_on
            ? CarbonImmutable::instance($type->starts_on)->startOfMonth()
            : $joined->startOfMonth();
        $cursor = $anchor;
        while ($cursor->endOfMonth()->lt($joined)) {
            $cursor = $cursor->addMonthsNoOverflow($frequency->value);
        }

        $through = $member->left_on
            ? CarbonImmutable::instance($member->left_on)->min($asOf)
            : $asOf;
        $dueDay = max(1, min(31, (int) ($type->due_day ?: 1)));
        $periods = collect();

        while ($cursor->lte($through)) {
            $dueOn = $cursor->day(min($dueDay, $cursor->daysInMonth));
            if ($dueOn->gte($joined) && $dueOn->lte($through)) {
                $periods->push(['period' => $cursor, 'due_on' => $dueOn]);
            }
            $cursor = $cursor->addMonthsNoOverflow($frequency->value);
        }

        return $periods;
    }

    /** Génère sans doublon les échéances dues et conserve tout montant historique. */
    public function sync(?CarbonImmutable $asOf = null): int
    {
        $asOf ??= CarbonImmutable::today();
        $created = 0;
        $types = FinancialType::query()
            ->where('kind', 'contribution')->where('is_active', true)->where('frequency_months', '>', 0)->get();

        DB::transaction(function () use ($types, $asOf, &$created): void {
            Member::query()->whereNotNull('joined_on')->whereDate('joined_on', '<=', $asOf)
                ->where(fn ($query) => $query->where('status', MemberStatus::Actif->value)->orWhereNotNull('left_on'))
                ->orderBy('id')
                ->chunkById(100, function ($members) use ($types, $asOf, &$created): void {
                    foreach ($members as $member) {
                        foreach ($types as $type) {
                            foreach ($this->duePeriods($member, $type, $asOf) as $period) {
                                $entry = Contribution::firstOrCreate(
                                    [
                                        'member_id' => $member->id,
                                        'year' => $period['period']->year,
                                        'month' => $period['period']->month,
                                        'type_code' => $type->code,
                                    ],
                                    [
                                        'choir_id' => $member->choir_id,
                                        'period_starts_on' => $period['period']->toDateString(),
                                        'due_on' => $period['due_on']->toDateString(),
                                        'due_amount' => (float) ($type->default_amount ?? 0),
                                        'paid_amount' => 0,
                                        'status' => $period['due_on']->lt($asOf) ? ContributionStatus::Retard : ContributionStatus::Due,
                                        'notes' => 'Échéance générée automatiquement.',
                                    ],
                                );
                                if ($entry->wasRecentlyCreated) {
                                    $created++;
                                } elseif ((float) $entry->paid_amount <= 0 && $period['due_on']->lt($asOf) && $entry->status === ContributionStatus::Due) {
                                    $entry->update(['status' => ContributionStatus::Retard]);
                                }
                            }
                        }
                    }
                });
        }, 3);

        return $created;
    }
}
