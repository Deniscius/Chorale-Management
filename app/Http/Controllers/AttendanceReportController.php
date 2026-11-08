<?php

namespace App\Http\Controllers;

use App\Enums\ParticipationStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\AttendanceIncident;
use App\Models\Rehearsal;
use App\Services\AttendanceRoster;
use App\Services\ChoraleIdentity;
use App\Services\Letterhead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class AttendanceReportController
{
    public function __invoke(Request $request, string $eventType, int $event, Letterhead $letterhead, ChoraleIdentity $identity)
    {
        return $this->report($request, $eventType, $event, $letterhead, $identity);
    }

    public function activityReport(Request $request, Activity $activity, Letterhead $letterhead, ChoraleIdentity $identity)
    {
        return $this->report($request, 'activite', $activity->id, $letterhead, $identity, true);
    }

    private function report(Request $request, string $eventType, int $event, Letterhead $letterhead, ChoraleIdentity $identity, bool $complete = false)
    {
        $canManageActivity = $request->user()->canManageModule(UserRole::MODULE_ACTIVITES);
        $canViewFinance = $complete ? Gate::allows('view-module', UserRole::MODULE_COMPTABILITE) : Gate::allows('view-attendance-finance');
        abort_unless(Gate::any(['view-attendance', 'view-attendance-finance']) || ($complete && ($canManageActivity || $canViewFinance)), 403);
        $options = $request->validate(['document' => ['nullable', 'in:rapport,fiche'], 'orientation' => ['nullable', 'in:portrait,landscape']]);
        $sheet = ! $complete && ($options['document'] ?? '') === 'fiche';
        $orientation = $options['orientation'] ?? ($complete ? 'landscape' : 'portrait');
        $roster = app(AttendanceRoster::class);

        if ($eventType === 'repetition') {
            $record = Rehearsal::query()->with(['attendanceFinalizer:id,name'])->findOrFail($event);
            $title = 'Répétition';
            $label = $record->venue ? 'Répétition — '.$record->venue : 'Répétition';
            $date = $record->date;
            $venue = $record->venue;
            $rows = $roster->rehearsalRows($record);
        } elseif ($eventType === 'activite') {
            $record = Activity::query()->with(['attendanceFinalizer:id,name', 'commission', 'organizer' => fn ($query) => $query->withTrashed(), 'author:id,name'])->findOrFail($event);
            $title = 'Activité';
            $label = $record->title;
            $date = $record->starts_at;
            $venue = $record->venue;
            $rows = $roster->activityRows($record);
        } else {
            abort(404);
        }

        abort_unless((int) $record->tenant_id === (int) $request->user()->tenant_id, 404);

        // Draft reports are useful before validation, but must never look official.
        abort_unless($complete || $record->attendance_finalized_at || Gate::allows('view-attendance'), 403);
        $showAttendance = $record->attendance_finalized_at || Gate::allows('view-attendance') || ($complete && $canManageActivity);
        if (! $showAttendance) {
            $rows = collect();
        }
        $finance = null;
        if ($canViewFinance && ! $sheet) {
            $incidents = AttendanceIncident::query()
                ->whereIn($eventType === 'activite' ? 'activity_participant_id' : 'rehearsal_attendance_id', $rows->pluck('id')->filter())
                ->where('status', '!=', AttendanceIncident::CANCELLED)
                ->with(['member' => fn ($query) => $query->withTrashed()])->orderBy('id')->get();
            $allTransactions = $record instanceof Activity
                ? $record->transactions()->with(['account', 'budget', 'member' => fn ($query) => $query->withTrashed()])->orderBy('transaction_date')->orderBy('id')->get()
                : collect();
            $transactions = $allTransactions->filter(fn ($transaction) => $transaction->isLocked())->values();
            $contributions = $record instanceof Activity
                ? $record->participationContributions()->with(['member' => fn ($query) => $query->withTrashed()])->orderBy('member_id')->get()
                : collect();
            $finance = [
                'incidents' => $incidents, 'transactions' => $transactions, 'contributions' => $contributions,
                'other_transactions' => $allTransactions->reject(fn ($transaction) => $transaction->isLocked())->values(),
                'receipts' => $transactions->where('type', TransactionType::Recette)->sum('amount'),
                'expenses' => $transactions->where('type', TransactionType::Depense)->sum('amount'),
                'pending' => $incidents->where('status', AttendanceIncident::PENDING)->sum('amount'),
                'waived' => $incidents->where('status', AttendanceIncident::WAIVED)->sum('amount'),
                'advances' => $record instanceof Activity
                    ? $record->memberExpenses()->with(['member' => fn ($query) => $query->withTrashed()])->withSum('repayments', 'amount')->orderBy('incurred_on')->get()
                    : collect(),
            ];
        }

        $counts = $rows->countBy(fn ($row): string => $row->status->value);
        $filename = ($complete ? 'rapport-activite-' : ($sheet ? 'fiche-presence-' : 'rapport-presence-')).$eventType.'-'.$event.'-'.$date->format('Y-m-d').'.html';

        return response()->view('documents.attendance-report', [
            ...$letterhead->viewData(),
            'identity' => $identity, 'record' => $record,
            'title' => $title, 'label' => $label, 'date' => $date, 'venue' => $venue, 'rows' => $rows,
            'counts' => $counts, 'statusLabels' => ParticipationStatus::labels(),
            'finance' => $finance,
            'complete' => $complete, 'sheet' => $sheet, 'orientation' => $orientation, 'showAttendance' => (bool) $showAttendance,
            'backUrl' => $complete ? route('activities.index') : (Gate::allows('view-attendance')
                ? route('attendance.sheet', ['type' => $eventType === 'activite' ? 'activity' : 'rehearsal', 'evenement' => $event])
                : (Gate::allows('view-attendance-finance')
                ? route('attendance.financial-report')
                : route('activities.index'))),
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
