<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\InstrumentMovement;
use App\Models\Member;
use App\Models\Performance;
use App\Models\Rehearsal;

final class PersonalNotebook
{
    public function __construct(private readonly MonthlyContributionArrears $arrears) {}

    /**
     * Assemble uniquement des données concernant le choriste connecté.
     * Les événements communs sont visibles, les activités ciblées exigent sa participation.
     *
     * @return array<string, mixed>
     */
    public function for(?Member $member, int $limit = 50): array
    {
        if (! $member) {
            return ['member' => null, 'timeline' => collect(), 'tasks' => collect(), 'correspondences' => collect(), 'documents' => collect(), 'contributions' => collect(), 'incidents' => collect(), 'attendance' => []];
        }

        $from = now();
        $notebookDays = max(7, (int) config('chorale.reminders.notebook_days', 45));
        $until = now()->addDays($notebookDays)->endOfDay();
        $upcomingStatuses = ['planifiee', 'en_cours', 'reportee'];

        $rehearsals = Rehearsal::query()
            ->whereIn('status', $upcomingStatuses)
            ->whereBetween('date', [$from->toDateString(), $until->toDateString()])
            ->orderBy('date')->get()
            ->map(fn (Rehearsal $item) => [
                'kind' => 'rehearsal', 'label' => 'Répétition', 'title' => 'Répétition de la chorale',
                'at' => $item->date->copy()->setTimeFromTimeString($item->starts_at ?: '00:00'),
                'venue' => $item->venue, 'detail' => $item->timeRange(), 'icon' => 'music',
            ]);

        $performances = Performance::query()
            ->whereIn('status', $upcomingStatuses)
            ->whereBetween('scheduled_at', [$from, $until])
            ->orderBy('scheduled_at')->get()
            ->map(fn (Performance $item) => [
                'kind' => 'performance', 'label' => 'Célébration / concert', 'title' => $item->title,
                'at' => $item->scheduled_at, 'venue' => $item->venue,
                'detail' => $item->type->label(), 'icon' => 'sparkles',
            ]);

        $activities = Activity::query()
            ->whereIn('status', $upcomingStatuses)
            ->whereBetween('starts_at', [$from, $until])
            ->where(function ($query) use ($member) {
                $query->whereDoesntHave('participantRecords')
                    ->orWhereHas('participantRecords', fn ($participants) => $participants->where('member_id', $member->id));
            })
            ->orderBy('starts_at')->get()
            ->map(fn (Activity $item) => [
                'kind' => 'activity', 'label' => 'Activité', 'title' => $item->title,
                'at' => $item->starts_at, 'venue' => $item->venue,
                'detail' => $item->type->label(), 'icon' => 'calendar',
            ]);

        $timeline = $rehearsals->concat($performances)->concat($activities)
            ->sortBy('at')->take($limit)->values();

        $contributions = $member->contributions()
            ->whereIn('status', ['due', 'partiel', 'retard'])
            ->where(fn ($query) => $query->where('year', '<', today()->year)
                ->orWhere(fn ($current) => $current->where('year', today()->year)->where('month', '<=', today()->month)))
            ->orderByDesc('year')->orderByDesc('month')->get();

        $incidents = $member->attendanceIncidents()->where('status', 'pending')->latest('occurred_on')->get();
        $incidentBreakdown = $incidents->groupBy('incident_type')->map(fn ($items) => [
            'count' => $items->count(),
            'amount' => (float) $items->sum('amount'),
        ])->all();
        $monthlyArrears = $this->arrears->for($member);
        $otherContributionOutstanding = (float) $contributions
            ->where('type_code', '!=', 'mensuelle')
            ->sum(fn ($item) => max(0, (float) $item->due_amount - (float) $item->paid_amount));
        $tasks = Correspondence::query()->open()->where('assigned_to', $member->id)->get()
            ->map(fn (Correspondence $item) => [
                'title' => 'Courrier à traiter', 'detail' => $item->subject,
                'date' => $item->correspondence_date, 'icon' => 'mail',
            ])->concat(
                InstrumentMovement::query()->with('instrument')->where('responsible_id', $member->id)
                    ->whereNull('actual_return_on')->get()->map(fn (InstrumentMovement $item) => [
                        'title' => 'Instrument à restituer', 'detail' => $item->instrument->name,
                        'date' => $item->expected_return_on, 'icon' => 'cube',
                    ])
            )->sortBy(fn (array $task) => $task['date']?->timestamp ?? PHP_INT_MAX)->values();
        $correspondences = Correspondence::query()
            ->where(function ($query) use ($member) {
                $query->where('recipient_member_id', $member->id)
                    ->orWhere(function ($shared) {
                        $shared->whereNull('recipient_member_id')->where('confidentiality', 'normal');
                    });
            })
            ->latest('correspondence_date')->latest('id')->take(12)->get();
        $documents = Document::query()
            ->readable($member->user)
            ->latest('document_date')->latest('id')->take(12)->get();
        $attendanceRows = $member->rehearsalAttendances()->get(['status'])
            ->concat($member->activityParticipants()->whereNotNull('checked_at')->get(['status']));

        return [
            'member' => $member,
            'timeline' => $timeline,
            'tasks' => $tasks,
            'correspondences' => $correspondences,
            'documents' => $documents,
            'contributions' => $contributions,
            'incidents' => $incidents,
            'attendance' => $attendanceRows->countBy(fn ($row) => $row->status->value)->all(),
            'notebookDays' => $notebookDays,
            'monthlyArrears' => $monthlyArrears,
            'outstanding' => $monthlyArrears['outstanding'] + $otherContributionOutstanding,
            'incidentAmount' => (float) $incidents->sum('amount'),
            'incidentBreakdown' => $incidentBreakdown,
            'until' => $until,
        ];
    }
}
