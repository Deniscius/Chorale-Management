<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Models\Activity;
use App\Models\Contribution;
use App\Models\FinancialType;
use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Creates one traceable participation contribution per activity and chorister. */
final class ActivityParticipationFee
{
    /**
     * @param iterable<int> $memberIds
     */
    public function createFor(Activity $activity, iterable $memberIds): int
    {
        if (! $activity->participation_fee_enabled || (float) $activity->participation_fee_amount <= 0) {
            return 0;
        }

        $type = $this->typeFor($activity);
        $startsAt = CarbonImmutable::instance($activity->starts_at);
        $dueOn = $activity->participation_fee_due_on
            ? CarbonImmutable::instance($activity->participation_fee_due_on)
            : $startsAt->startOfDay();
        $members = Member::query()->active()->whereIn('id', collect($memberIds)->map(fn ($id) => (int) $id)->unique()->all())->get();
        $created = 0;

        foreach ($members as $member) {
            $contribution = Contribution::firstOrCreate(
                ['activity_id' => $activity->id, 'member_id' => $member->id],
                [
                    'type_code' => $type->code,
                    'choir_id' => $member->choir_id,
                    'year' => $startsAt->year,
                    'month' => $startsAt->month,
                    'period_starts_on' => $startsAt->toDateString(),
                    'due_on' => $dueOn->toDateString(),
                    'due_amount' => $activity->participation_fee_amount,
                    'paid_amount' => 0,
                    'status' => $dueOn->lt(today()) ? ContributionStatus::Retard : ContributionStatus::Due,
                    'notes' => 'Participation à l’activité « '.$activity->title.' ».',
                ],
            );
            $created += $contribution->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /** Apply a newly configured fee to participants already registered. */
    public function syncExisting(Activity $activity): int
    {
        return $this->createFor($activity, $activity->participants()->pluck('members.id'));
    }

    private function typeFor(Activity $activity): FinancialType
    {
        $code = 'participation-activite-'.$activity->id;

        $type = FinancialType::query()->where('code', $code)->first();
        if ($type) {
            return $type;
        }

        $type = new FinancialType;
        $type->forceFill([
            'code' => $code,
            'kind' => 'contribution',
            'label' => 'Participation — '.$activity->title,
            'default_amount' => $activity->participation_fee_amount,
            'frequency_months' => 0,
            'starts_on' => null,
            'due_day' => null,
            'is_active' => true,
        ]);
        $type->save();

        return $type;
    }
}
