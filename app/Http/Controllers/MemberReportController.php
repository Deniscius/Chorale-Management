<?php

namespace App\Http\Controllers;

use App\Enums\MemberStatus;
use App\Enums\UserRole;
use App\Enums\Voice;
use App\Models\Member;
use App\Services\ChoraleIdentity;
use App\Services\Letterhead;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\HeaderUtils;

final class MemberReportController extends Controller
{
    public function __invoke(Letterhead $letterhead, ChoraleIdentity $identity)
    {
        Gate::authorize('manage-module', UserRole::MODULE_MEMBERS);

        $members = Member::query()
            ->with('choir:id,name')
            ->orderBy('last_name')->orderBy('first_name')->get();
        $statusCounts = $members->countBy(fn (Member $member): string => $member->status->value);
        $voiceCounts = $members->filter->voice->countBy(fn (Member $member): string => $member->voice->value);
        $filename = 'rapport-general-choristes-'.today()->format('Y-m-d').'.html';

        return response()->view('documents.member-report', [
            'header' => $letterhead->headerDataUri(),
            'identity' => $identity,
            'members' => $members,
            'statusCounts' => $statusCounts,
            'voiceCounts' => $voiceCounts,
            'statusLabels' => MemberStatus::labels(),
            'voiceLabels' => Voice::labels(),
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
