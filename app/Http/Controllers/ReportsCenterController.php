<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use Illuminate\Http\Request;

/** A single entry point to the official documents available to the current role. */
final class ReportsCenterController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $isGlobal = in_array($user->role, [UserRole::Admin, UserRole::Directeur], true);
        $commissions = ChoirCommission::inStatus('active')->when(! $isGlobal, fn ($query) => $query->where(fn ($q) => $q->where('leader_id', $user->id)->orWhere('associate_id', $user->id)))->orderBy('name')->get();

        return view('reports.index', [
            'commissions' => $commissions,
            'canMembers' => $user->canManageModule(UserRole::MODULE_MEMBERS),
            'canActivities' => $user->canManageModule(UserRole::MODULE_ACTIVITES),
            'canAccounting' => $user->canManageModule(UserRole::MODULE_COMPTABILITE),
            'canAttendanceFinance' => $user->can('view-attendance-finance'),
            'canAudit' => $user->can('manage-users'),
        ]);
    }
}
