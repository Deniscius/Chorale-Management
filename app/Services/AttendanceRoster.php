<?php

namespace App\Services;

use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use Illuminate\Support\Collection;

final class AttendanceRoster
{
    public function rehearsalMembers(Rehearsal $rehearsal): Collection
    {
        $recorded = $rehearsal->attendances()->pluck('member_id');

        return Member::withTrashed()->where(fn ($query) => $query
            ->where(fn ($active) => $active->active()->whereNull('deleted_at'))
            ->orWhereIn('id', $recorded))
            ->orderBy('last_name')->orderBy('first_name')->get();
    }

    public function rehearsalRows(Rehearsal $rehearsal): Collection
    {
        $existing = $rehearsal->attendances()->with(['member' => fn ($query) => $query->withTrashed()])->get()->keyBy('member_id');
        if ($rehearsal->attendance_finalized_at) {
            return $existing->sortBy([['member.last_name', 'asc'], ['member.first_name', 'asc']])->values();
        }

        return $this->rehearsalMembers($rehearsal)->map(function (Member $member) use ($existing): RehearsalAttendance {
            $row = $existing->get($member->id) ?? new RehearsalAttendance(['member_id' => $member->id, 'status' => ParticipationStatus::Invite]);
            $row->setRelation('member', $member);

            return $row;
        });
    }

    public function activityRows(Activity $activity): Collection
    {
        return $activity->participantRecords()->with(['member' => fn ($query) => $query->withTrashed()])
            ->get()->sortBy([['member.last_name', 'asc'], ['member.first_name', 'asc']])->values();
    }
}
