<?php

namespace App\Http\Controllers;

use App\Enums\ParticipationStatus;
use App\Enums\TransactionType;
use App\Models\Activity;
use App\Models\AttendanceIncident;
use App\Models\Rehearsal;
use App\Services\ChoraleIdentity;
use App\Services\Letterhead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class AttendanceReportController
{
    public function __invoke(Request $request, string $eventType, int $event, Letterhead $letterhead, ChoraleIdentity $identity)
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

        abort_unless((int) $record->tenant_id === (int) $request->user()->tenant_id, 404);

        // Draft reports are useful before validation, but must never look official.
        abort_unless($record->attendance_finalized_at || Gate::allows('view-attendance'), 403);
        $finance = null;
        if (Gate::allows('view-attendance-finance')) {
            $incidents = AttendanceIncident::query()
                ->whereIn($eventType === 'activite' ? 'activity_participant_id' : 'rehearsal_attendance_id', $rows->pluck('id'))
                ->where('status', '!=', AttendanceIncident::CANCELLED)
                ->with('member')->orderBy('id')->get();
            $transactions = $record instanceof Activity
                ? $record->transactions()->validated()->with(['account', 'member'])->orderBy('transaction_date')->orderBy('id')->get()
                : collect();
            $contributions = $record instanceof Activity
                ? $record->participationContributions()->with('member')->orderBy('member_id')->get()
                : collect();
            $finance = [
                'incidents' => $incidents, 'transactions' => $transactions, 'contributions' => $contributions,
                'receipts' => $transactions->where('type', TransactionType::Recette)->sum('amount'),
                'expenses' => $transactions->where('type', TransactionType::Depense)->sum('amount'),
                'pending' => $incidents->where('status', AttendanceIncident::PENDING)->sum('amount'),
                'waived' => $incidents->where('status', AttendanceIncident::WAIVED)->sum('amount'),
                'advances' => $record instanceof Activity
                    ? $record->memberExpenses()->with('member')->withSum('repayments', 'amount')->orderBy('incurred_on')->get()
                    : collect(),
            ];
        }

        $counts = $rows->countBy(fn ($row): string => $row->status->value);
        $filename = 'rapport-presence-'.$eventType.'-'.$date->format('Y-m-d').'.html';

        return response()->view('documents.attendance-report', [
            'header' => $letterhead->headerDataUri(), 'headerHeightMm' => $letterhead->headerHeightMm(),
            'identity' => $identity, 'record' => $record,
            'title' => $title, 'label' => $label, 'date' => $date, 'venue' => $venue, 'rows' => $rows,
            'counts' => $counts, 'statusLabels' => ParticipationStatus::labels(),
            'finance' => $finance,
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
