<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;

/** A single entry point to the official documents available to the current role. */
final class ReportsCenterController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return view('reports.index', [
            'canMembers' => $user->canManageModule(UserRole::MODULE_MEMBERS),
            'canActivities' => $user->canManageModule(UserRole::MODULE_ACTIVITES),
            'canAccounting' => $user->canManageModule(UserRole::MODULE_COMPTABILITE),
            'canAttendanceFinance' => $user->can('view-attendance-finance'),
            'canAudit' => $user->can('manage-users'),
        ]);
    }
}
