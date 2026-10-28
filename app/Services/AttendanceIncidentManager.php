<?php

namespace App\Services;

use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\AttendanceIncident;
use App\Models\AttendanceRule;
use App\Models\Rehearsal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AttendanceIncidentManager
{
    public function finalizeRehearsal(Rehearsal $rehearsal): void
    {
        $rule = AttendanceRule::query()->firstOrCreate([]);
        $activeIds = [];

        foreach ($rehearsal->attendances()->get() as $attendance) {
            $data = $this->incidentData(
                $attendance->status,
                (int) ($attendance->minutes_late ?? 0),
                'rehearsal',
                $rule,
                $rehearsal->date,
            );
            if ($data === null) {
                continue;
            }

            $incident = AttendanceIncident::query()->updateOrCreate(
                ['rehearsal_attendance_id' => $attendance->id],
                [...$data, 'member_id' => $attendance->member_id, 'activity_participant_id' => null,
                    'source_type' => 'rehearsal', 'occurred_on' => $rehearsal->date,
                    'event_label' => Str::limit('Répétition'.($rehearsal->venue ? ' — '.$rehearsal->venue : ''), 255, ''),
                    'status' => AttendanceIncident::PENDING, 'reviewed_by' => null,
                    'reviewed_at' => null, 'review_note' => null],
            );
            $activeIds[] = $incident->id;
        }

        AttendanceIncident::query()
            ->whereHas('rehearsalAttendance', fn ($query) => $query->where('rehearsal_id', $rehearsal->id))
            ->when($activeIds !== [], fn ($query) => $query->whereNotIn('id', $activeIds))
            ->get()
            ->each(fn (AttendanceIncident $incident) => $incident->update(['status' => AttendanceIncident::CANCELLED]));
    }

    public function finalizeActivity(Activity $activity): void
    {
        $rule = AttendanceRule::query()->firstOrCreate([]);
        $activeIds = [];

        foreach ($activity->participantRecords()->get() as $participant) {
            $data = $this->incidentData(
                $participant->status,
                (int) ($participant->minutes_late ?? 0),
                'activity',
                $rule,
                $activity->starts_at,
            );
            if ($data === null) {
                continue;
            }

            $incident = AttendanceIncident::query()->updateOrCreate(
                ['activity_participant_id' => $participant->id],
                [...$data, 'member_id' => $participant->member_id, 'rehearsal_attendance_id' => null,
                    'source_type' => 'activity', 'occurred_on' => $activity->starts_at->toDateString(),
                    'event_label' => Str::limit($activity->title, 255, ''), 'status' => AttendanceIncident::PENDING,
                    'reviewed_by' => null, 'reviewed_at' => null, 'review_note' => null],
            );
            $activeIds[] = $incident->id;
        }

        AttendanceIncident::query()
            ->whereHas('activityParticipant', fn ($query) => $query->where('activity_id', $activity->id))
            ->when($activeIds !== [], fn ($query) => $query->whereNotIn('id', $activeIds))
            ->get()
            ->each(fn (AttendanceIncident $incident) => $incident->update(['status' => AttendanceIncident::CANCELLED]));
    }

    public function reopenRehearsal(Rehearsal $rehearsal): void
    {
        DB::transaction(function () use ($rehearsal): void {
            AttendanceIncident::query()
                ->whereHas('rehearsalAttendance', fn ($query) => $query->where('rehearsal_id', $rehearsal->id))
                ->get()
                ->each(fn (AttendanceIncident $incident) => $incident->update(['status' => AttendanceIncident::CANCELLED]));
            $rehearsal->forceFill(['attendance_finalized_at' => null, 'attendance_finalized_by' => null])->save();
        });
    }

    public function reopenActivity(Activity $activity): void
    {
        DB::transaction(function () use ($activity): void {
            AttendanceIncident::query()
                ->whereHas('activityParticipant', fn ($query) => $query->where('activity_id', $activity->id))
                ->get()
                ->each(fn (AttendanceIncident $incident) => $incident->update(['status' => AttendanceIncident::CANCELLED]));
            $activity->forceFill(['attendance_finalized_at' => null, 'attendance_finalized_by' => null])->save();
        });
    }

    private function incidentData(ParticipationStatus|string $status, int $minutesLate, string $source, AttendanceRule $rule, \DateTimeInterface $occurredOn): ?array
    {
        $value = $status instanceof ParticipationStatus ? $status->value : $status;
        $effective = $rule->effective_from === null || $rule->effective_from->lte($occurredOn);
        $priced = $rule->financial_tracking_enabled && $effective;

        if ($value === ParticipationStatus::Absent->value) {
            $amount = $source === 'rehearsal' ? $rule->rehearsal_absence_amount : $rule->activity_absence_amount;
            $type = 'absence';
        } elseif ($value === ParticipationStatus::Late->value && $minutesLate >= $rule->late_threshold_minutes) {
            $amount = $rule->late_amount;
            $type = 'late';
        } else {
            return null;
        }

        return [
            'incident_type' => $type,
            'minutes_late' => $type === 'late' ? $minutesLate : null,
            'amount' => $priced ? $amount : 0,
            'rule_snapshot' => [
                'financial_tracking_enabled' => $rule->financial_tracking_enabled,
                'effective_from' => $rule->effective_from?->toDateString(),
                'late_threshold_minutes' => $rule->late_threshold_minutes,
                'configured_amount' => (string) $amount,
            ],
        ];
    }
}
