<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Services\ChoraleIdentity;
use App\Services\Letterhead;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** Official evidence document scoped to one commission and its delegated officers. */
final class CommissionReportController
{
    public function __invoke(Request $request, ChoirCommission $commission, Letterhead $letterhead, ChoraleIdentity $identity)
    {
        $user = $request->user();
        abort_unless((int) $commission->tenant_id === (int) $user->tenant_id, 404);
        $isGlobal = in_array($user->role, [UserRole::Admin, UserRole::Directeur], true);
        abort_unless($isGlobal || (int) $commission->leader_id === (int) $user->id || (int) $commission->associate_id === (int) $user->id, 403);
        abort_unless($commission->status === 'active', 404);

        $commission->load([
            'leader:id,name', 'associate:id,name',
            'members' => fn ($query) => $query->orderBy('last_name')->orderBy('first_name'),
            'activities' => fn ($query) => $query->withCount('participants')->orderByDesc('starts_at')->limit(100),
        ]);

        return response()->view('documents.commission-report', [
            ...$letterhead->viewData(),
            'identity' => $identity, 'commission' => $commission,
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', 'rapport-commission-'.$commission->id.'-'.today()->format('Y-m-d').'.html'),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
