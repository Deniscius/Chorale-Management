<?php

namespace App\Http\Controllers;

use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\Rehearsal;
use App\Services\ChoraleIdentity;
use App\Services\Letterhead;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class AttendanceReportController
{
    public function __invoke(string $eventType, int $event, Letterhead $letterhead, ChoraleIdentity $identity)
    {
        abort_unless(Gate::any(['view-attendance', 'view-attendance-finance']), 403);

        if ($eventType === 'repetition') {
            $record = Rehearsal::query()->with(['attendances.member', 'attendanceFinalizer:id,name'])->findOrFail($event);
            $title = 'Répétition';
            $label = $record->venue ? 'Répétition — '.$record->venue : 'Répétition';
            $date = $record->date;
            $venue = $record->venue;
            $rows = $record->attendances->sortBy('member.full_name')->values();
        } elseif ($eventType === 'activite') {
            $record = Activity::query()->with(['participantRecords.member', 'attendanceFinalizer:id,name'])->findOrFail($event);
            $title = 'Activité';
            $label = $record->title;
            $date = $record->starts_at;
            $venue = $record->venue;
            $rows = $record->participantRecords->sortBy('member.full_name')->values();
        } else {
            abort(404);
        }

        abort_unless($record->attendance_finalized_at, 404, 'Cette fiche de présence n’est pas finalisée.');

        $counts = $rows->countBy(fn ($row): string => $row->status->value);
        $filename = 'rapport-presence-'.$eventType.'-'.$date->format('Y-m-d').'.html';

        return response()->view('documents.attendance-report', [
            'header' => $letterhead->headerDataUri(), 'identity' => $identity, 'record' => $record,
            'title' => $title, 'label' => $label, 'date' => $date, 'venue' => $venue, 'rows' => $rows,
            'counts' => $counts, 'statusLabels' => ParticipationStatus::labels(),
            'backUrl' => Gate::allows('view-attendance-finance')
                ? route('attendance.financial-report')
                : route('attendance.sheet'),
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
