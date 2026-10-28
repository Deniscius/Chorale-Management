<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use App\Models\AttendanceIncident;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'voice' => ['nullable', 'string', 'in:soprano,alto,contretenor,tenor,bass'],
            'status' => ['nullable', 'string', 'in:actif,inactif,invite,honoraire,externe'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $members = Member::query()
            ->search($validated['q'] ?? '')
            ->when($validated['voice'] ?? null, fn (Builder $query, string $voice) => $query->where('voice', $voice))
            ->when($validated['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json($members->through(fn (Member $member) => $this->basic($member)));
    }

    public function show(Request $request, Member $member): JsonResponse
    {
        /** @var ApiClient $client */
        $client = $request->attributes->get('apiClient');
        $data = $this->basic($member);

        if ($client->can('attendance:read')) {
            $rehearsals = $member->rehearsalAttendances()->selectRaw('status, COUNT(*) AS total, COALESCE(SUM(minutes_late), 0) AS minutes')->groupBy('status')->get()->keyBy(fn ($row) => $row->status->value);
            $activities = $member->activityParticipants()->selectRaw('status, COUNT(*) AS total, COALESCE(SUM(minutes_late), 0) AS minutes')->groupBy('status')->get()->keyBy(fn ($row) => $row->status->value);
            $data['attendance'] = collect(['present', 'late', 'absent', 'excuse'])->mapWithKeys(fn (string $status) => [$status => (int) ($rehearsals->get($status)?->total ?? 0) + (int) ($activities->get($status)?->total ?? 0),
            ])->all();
            $data['attendance']['minutes_late'] = collect([$rehearsals, $activities])->sum(fn ($rows) => (int) $rows->sum('minutes'));
        }

        if ($client->can('finance:read')) {
            $contributions = $member->contributions()->selectRaw('COALESCE(SUM(due_amount), 0) AS due, COALESCE(SUM(paid_amount), 0) AS paid')->toBase()->first();
            $data['finance'] = [
                'contributions_due' => (float) $contributions->due,
                'contributions_paid' => (float) $contributions->paid,
                'contributions_balance' => max(0, (float) $contributions->due - (float) $contributions->paid),
                'pending_incidents' => $member->attendanceIncidents()->where('status', AttendanceIncident::PENDING)->count(),
                'pending_incident_amount' => (float) $member->attendanceIncidents()->where('status', AttendanceIncident::PENDING)->sum('amount'),
            ];
        }

        return response()->json(['data' => $data]);
    }

    private function basic(Member $member): array
    {
        return [
            'id' => $member->id,
            'first_name' => $member->first_name,
            'last_name' => $member->last_name,
            'full_name' => $member->full_name,
            'voice' => $member->voice?->value,
            'part' => $member->part,
            'status' => $member->status->value,
            'joined_on' => $member->joined_on?->toDateString(),
            'updated_at' => $member->updated_at?->toIso8601String(),
        ];
    }
}
