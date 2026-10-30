<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
use App\Models\ContributionCounterReset;
use App\Models\ContributionExemption;
use App\Enums\UserRole;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class ContributionArrears
{
    /** 
     * A historical cut-off changes only the displayed current arrears.
     * Older debts and payment/ledger records remain intact.
     */
    public function startNewCounter(Member $member, CarbonImmutable $date, string $reason, ?string $typeCode = null): ContributionCounterReset
    {
        Gate::authorize('manage-module', UserRole::MODULE_COMPTABILITE);
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Un motif de cinq caractères minimum est obligatoire.']);
        }

        return DB::transaction(fn () => ContributionCounterReset::create([
            'member_id' => $member->id,
            'type_code' => $typeCode,
            'starts_on' => $date->toDateString(),
            'reason' => trim($reason),
            'approved_by' => auth()->id(),
        ]));
    }

    /**
     * Exempt unpaid installments while keeping every previously recorded payment.
     * A partially settled installment must be reviewed separately by the treasurer.
     */
    public function exemptUnpaid(Member $member, CarbonImmutable $from, CarbonImmutable $until, string $typeCode, string $reason): int
    {
        Gate::authorize('manage-module', UserRole::MODULE_COMPTABILITE);
        if ($until->lt($from) || mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Période ou motif invalide (cinq caractères minimum).']);
        }

        return DB::transaction(function () use ($member, $from, $until, $typeCode, $reason): int {
            $entries = Contribution::query()->where('member_id', $member->id)
                ->where('type_code', $typeCode)
                ->whereDate('period_starts_on', '>=', $from->toDateString())
                ->whereDate('period_starts_on', '<=', $until->toDateString())
                ->lockForUpdate()->get();

            if ($entries->contains(fn (Contribution $entry) => (float) $entry->paid_amount > 0
                && $entry->status !== ContributionStatus::Payee)) {
                throw ValidationException::withMessages([
                    'period' => 'Des échéances partiellement payées nécessitent un traitement comptable séparé.',
                ]);
            }

            $exemption = ContributionExemption::create([
                'member_id' => $member->id,
                'type_code' => $typeCode,
                'starts_on' => $from->toDateString(),
                'ends_on' => $until->toDateString(),
                'clears_prior_arrears' => true,
                'reason' => trim($reason),
                'approved_by' => auth()->id(),
            ]);
            $count = 0;
            foreach ($entries as $entry) {
                if ((float) $entry->paid_amount !== 0.0 || $entry->status === ContributionStatus::Exoneree) {
                    continue;
                }
                $entry->update([
                    'exemption_id' => $exemption->id,
                    'due_amount' => 0,
                    'status' => ContributionStatus::Exoneree,
                    'notes' => 'Exonération : '.trim($reason),
                ]);
                $count++;
            }

            return $count;
        }, 3);
    }

    /** Current arrears for one member, after the latest applicable cut-off. */
    public function currentArrears(Member $member, ?string $typeCode = null): float
    {
        $reset = ContributionCounterReset::query()->where('member_id', $member->id)
            ->where(function ($q) use ($typeCode): void {
                $q->whereNull('type_code');
                if ($typeCode !== null) {
                    $q->orWhere('type_code', $typeCode);
                }
            })
            ->orderByDesc('starts_on')->orderByDesc('id')->first();

        return (float) Contribution::query()
            ->where('member_id', $member->id)
            ->when($typeCode !== null, fn ($q) => $q->where('type_code', $typeCode))
            ->when($reset, fn ($q) => $q->whereDate('period_starts_on', '>=', $reset->starts_on))
            ->whereNotIn('status', [ContributionStatus::Exoneree->value, ContributionStatus::Payee->value])
            ->whereDate('due_on', '<', today())
            ->selectRaw('COALESCE(SUM(CASE WHEN due_amount > paid_amount THEN due_amount - paid_amount ELSE 0 END), 0) AS arrears')
            ->value('arrears');
    }
}
