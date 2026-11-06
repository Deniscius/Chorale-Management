<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class MemberDossierController extends Controller
{
    public function __invoke(Request $request, Member $member): View
    {
        abort_unless((int) $member->tenant_id === (int) $request->user()->tenant_id, 404);
        Gate::authorize('view-member-dossier', $member);

        $canViewAttendance = Gate::allows('view-member-attendance', $member);
        $canViewFinance = Gate::allows('view-member-finance', $member);

        $member->load(['choir', 'user']);

        $rehearsalAttendances = $canViewAttendance
            ? $member->rehearsalAttendances()->with('rehearsal:id,date,venue')->latest('checked_at')->limit(100)->get()
            : collect();
        $activityParticipations = $canViewAttendance
            ? $member->activityParticipants()->with('activity:id,title,starts_at')->latest('checked_at')->limit(100)->get()
            : collect();
        $incidents = ($canViewAttendance || $canViewFinance)
            ? $member->attendanceIncidents()->latest('occurred_on')->limit(100)->get()
            : collect();
        $contributions = $canViewFinance
            ? $member->contributions()->with('activity:id,title')->latest('year')->latest('month')->limit(120)->get()
            : collect();
        $transactions = $canViewFinance
            ? $member->transactions()->validated()->latest('transaction_date')->limit(50)->get()
            : collect();

        $attendance = $rehearsalAttendances->map(fn ($row) => [
            'date' => $row->rehearsal->date,
            'event' => $row->rehearsal->venue ? 'Répétition — '.$row->rehearsal->venue : 'Répétition',
            'status' => $row->status,
            'minutes_late' => $row->minutes_late,
            'excuse_reason' => $row->excuse_reason,
        ])->concat($activityParticipations->map(fn ($row) => [
            'date' => $row->activity->starts_at,
            'event' => $row->activity->title,
            'status' => $row->status,
            'minutes_late' => $row->minutes_late,
            'excuse_reason' => $row->excuse_reason,
        ]))->sortByDesc('date')->values();

        return view('members.show', compact(
            'member', 'canViewAttendance', 'canViewFinance', 'attendance', 'incidents', 'contributions', 'transactions'
        ));
    }
}
