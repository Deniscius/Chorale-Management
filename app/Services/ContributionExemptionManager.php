<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\ContributionExemption;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ContributionExemptionManager
{
    /**
     * Enregistre une exonération et applique immédiatement sa décision aux
     * échéances impayées déjà présentes. Les paiements, y compris partiels,
     * ne sont jamais modifiés.
     */
    public function create(array $attributes): array
    {
        return DB::transaction(function () use ($attributes): array {
            $exemption = ContributionExemption::create($attributes);
            $updated = $this->applyToExistingContributions($exemption);

            app(ContributionSchedule::class)->sync();

            return [$exemption, $updated];
        }, 3);
    }

    /**
     * @return int Nombre d'échéances actualisées.
     */
    public function applyToExistingContributions(ContributionExemption $exemption): int
    {
        $startsOn = CarbonImmutable::instance($exemption->starts_on)->startOfDay();
        $endsOn = $exemption->ends_on
            ? CarbonImmutable::instance($exemption->ends_on)->endOfDay()
            : null;
        $updated = 0;

        Contribution::query()
            ->where('member_id', $exemption->member_id)
            ->where('type_code', $exemption->type_code)
            ->where('paid_amount', '<=', 0)
            ->orderBy('id')
            ->each(function (Contribution $contribution) use ($exemption, $startsOn, $endsOn, &$updated): void {
                $period = $this->periodFor($contribution);
                $isPriorArrear = $exemption->clears_prior_arrears && $period->lt($startsOn);
                $isCovered = $period->gte($startsOn) && ($endsOn === null || $period->lte($endsOn));

                if (! $isPriorArrear && ! $isCovered) {
                    return;
                }

                if ($contribution->exemption_id === $exemption->id
                    && (float) $contribution->due_amount === 0.0
                    && $contribution->status === ContributionStatus::Exoneree) {
                    return;
                }

                $contribution->update([
                    'exemption_id' => $exemption->id,
                    'due_amount' => 0,
                    'status' => ContributionStatus::Exoneree,
                    'notes' => $isPriorArrear
                        ? 'Arriéré annulé par exonération : '.($exemption->reason ?: 'décision de la chorale').'.'
                        : 'Exonération : '.($exemption->reason ?: 'décision de la chorale').'.',
                ]);
                $updated++;
            });

        return $updated;
    }

    private function periodFor(Contribution $contribution): CarbonImmutable
    {
        if ($contribution->period_starts_on) {
            return CarbonImmutable::instance($contribution->period_starts_on)->startOfDay();
        }

        if ($contribution->due_on) {
            return CarbonImmutable::instance($contribution->due_on)->startOfDay();
        }

        return CarbonImmutable::create($contribution->year, max(1, $contribution->month), 1)->startOfDay();
    }
}
