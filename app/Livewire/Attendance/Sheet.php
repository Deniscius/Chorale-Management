<?php

namespace App\Livewire\Attendance;

use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Services\ActivityParticipationFee;
use App\Services\AttendanceIncidentManager;
use App\Services\AttendanceRoster;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

final class Sheet extends Component
{
    #[Url(as: 'type', except: 'rehearsal')]
    public string $eventType = 'rehearsal';

    #[Url(as: 'evenement')]
    public ?int $eventId = null;

    public string $memberSearch = '';

    public array $selectedMemberIds = [];

    /** @var array<int, array{member_id:int,name:string,status:string,minutes_late:string,excuse_reason:string}> */
    public array $rows = [];

    public function mount(): void
    {
        Gate::authorize('view-attendance');
        abort_unless(in_array($this->eventType, ['rehearsal', 'activity'], true), 404);
        if ($this->eventId) {
            $this->loadSheet();
        }
    }

    public function updatedEventType(): void
    {
        abort_unless(in_array($this->eventType, ['rehearsal', 'activity'], true), 404);
        $this->eventId = null;
        $this->rows = [];
        $this->selectedMemberIds = [];
        $this->resetValidation();
        unset($this->events, $this->selectedEvent, $this->availableMembers);
    }

    public function updatedEventId(): void
    {
        $this->selectedMemberIds = [];
        $this->resetValidation();
        unset($this->selectedEvent, $this->availableMembers);
        $this->loadSheet();
    }

    public function loadSheet(): void
    {
        Gate::authorize('view-attendance');
        abort_unless(in_array($this->eventType, ['rehearsal', 'activity'], true), 404);
        $this->rows = [];
        if (! $this->eventId) {
            return;
        }

        $roster = app(AttendanceRoster::class);
        $records = $this->eventType === 'rehearsal'
            ? $roster->rehearsalRows(Rehearsal::query()->findOrFail($this->eventId))
            : $roster->activityRows(Activity::query()->findOrFail($this->eventId));
        foreach ($records as $record) {
            if ($record->member) {
                $status = $record->status === ParticipationStatus::Invite ? '' : $record->status->value;
                $this->rows[] = $this->row($record->member, $status, $record->minutes_late, $record->excuse_reason);
            }
        }
    }

    public function addSelectedMembers(): void
    {
        Gate::authorize('manage-attendance');
        abort_unless($this->eventType === 'activity' && $this->eventId, 422);
        $data = $this->validate([
            'selectedMemberIds' => ['required', 'array', 'min:1', 'max:500'],
            'selectedMemberIds.*' => ['required', 'integer', 'distinct'],
        ]);
        $ids = array_map('intval', $data['selectedMemberIds']);
        $eligible = Member::query()->activityEligible()->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($eligible) !== count($ids)) {
            throw ValidationException::withMessages(['selectedMemberIds' => 'Sélectionnez des choristes actifs ou intervenants extérieurs de votre chorale.']);
        }
        DB::transaction(function () use ($eligible): void {
            $event = Activity::query()->lockForUpdate()->findOrFail($this->eventId);
            abort_if($event->attendance_finalized_at !== null, 422, 'Rouvrez la fiche avant de modifier les participants.');
            $existing = $event->participantRecords()->pluck('member_id')->map(fn ($id) => (int) $id)->all();
            $newIds = array_values(array_diff($eligible, $existing));
            foreach ($newIds as $id) {
                $event->participantRecords()->create(['member_id' => $id, 'status' => ParticipationStatus::Invite]);
            }
            app(ActivityParticipationFee::class)->createFor($event, $newIds);
        }, 3);
        $this->selectedMemberIds = [];
        unset($this->availableMembers, $this->selectedEvent);
        // Keep unsaved attendance edits for members already on the sheet.
        $draft = collect($this->rows)->keyBy('member_id');
        $this->loadSheet();
        $this->rows = collect($this->rows)->map(fn ($row) => $draft->get($row['member_id'], $row))->all();
        $this->dispatch('toast', type: 'success', message: 'Participants ajoutés à la fiche de présence.');
    }

    public function selectVisibleMembers(): void
    {
        Gate::authorize('manage-attendance');
        $this->selectedMemberIds = $this->availableMembers->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    #[Computed]
    public function availableMembers()
    {
        if ($this->eventType !== 'activity' || ! $this->eventId || $this->isFinalized()) {
            return collect();
        }

        return Member::query()->activityEligible()
            ->whereDoesntHave('activityParticipants', fn ($query) => $query->where('activity_id', $this->eventId))
            ->when(trim($this->memberSearch) !== '', fn ($query) => $query->search(trim($this->memberSearch)))
            ->orderBy('last_name')->orderBy('first_name')->limit(500)->get();
    }

    public function markAllPresent(): void
    {
        Gate::authorize('manage-attendance');
        abort_if($this->isFinalized(), 422, 'Cette fiche est finalisée.');
        foreach ($this->rows as &$row) {
            if ($row['status'] === '') {
                $row['status'] = ParticipationStatus::Present->value;
            }
        }
    }

    public function saveDraft(): void
    {
        Gate::authorize('manage-attendance');
        abort_if($this->isFinalized(), 422, 'Cette fiche est finalisée.');
        $this->validateRows(false);

        DB::transaction(function (): void {
            $event = $this->eventType === 'rehearsal'
                ? Rehearsal::query()->lockForUpdate()->findOrFail($this->eventId)
                : Activity::query()->lockForUpdate()->findOrFail($this->eventId);
            abort_if($event->attendance_finalized_at !== null, 422, 'Cette fiche est finalisée.');
            $this->persistRows();
        });
        $this->dispatch('toast', type: 'success', message: 'Brouillon de présence enregistré.');
        unset($this->selectedEvent);
    }

    public function finalize(): void
    {
        Gate::authorize('finalize-attendance');
        abort_if($this->isFinalized(), 422, 'Cette fiche est déjà finalisée.');
        $this->validateRows(true);

        DB::transaction(function (): void {
            $event = $this->eventType === 'rehearsal'
                ? Rehearsal::query()->lockForUpdate()->findOrFail($this->eventId)
                : Activity::query()->lockForUpdate()->findOrFail($this->eventId);
            abort_if($event->attendance_finalized_at !== null, 422, 'Cette fiche est déjà finalisée.');
            $this->persistRows();
            if ($this->eventType === 'rehearsal') {
                $event->forceFill(['attendance_finalized_at' => now(), 'attendance_finalized_by' => auth()->id()])->save();
                app(AttendanceIncidentManager::class)->finalizeRehearsal($event);
            } else {
                $event->forceFill(['attendance_finalized_at' => now(), 'attendance_finalized_by' => auth()->id()])->save();
                app(AttendanceIncidentManager::class)->finalizeActivity($event);
            }
        });

        unset($this->selectedEvent, $this->events);
        $this->dispatch('toast', type: 'success', message: 'Fiche finalisée et transmise au rapport comptable.');
    }

    public function reopen(): void
    {
        Gate::authorize('finalize-attendance');
        abort_unless($this->isFinalized(), 422, 'Cette fiche est déjà modifiable.');

        if ($this->eventType === 'rehearsal') {
            app(AttendanceIncidentManager::class)->reopenRehearsal(Rehearsal::findOrFail($this->eventId));
        } else {
            app(AttendanceIncidentManager::class)->reopenActivity(Activity::findOrFail($this->eventId));
        }
        unset($this->selectedEvent, $this->events);
        $this->dispatch('toast', type: 'success', message: 'Fiche rouverte. Les incidents précédents ont été annulés.');
    }

    #[Computed]
    public function events()
    {
        return $this->eventType === 'rehearsal'
            ? Rehearsal::query()->orderByDesc('date')->get()
            : Activity::query()->orderByDesc('starts_at')->get();
    }

    #[Computed]
    public function selectedEvent(): Rehearsal|Activity|null
    {
        if (! $this->eventId) {
            return null;
        }

        return $this->eventType === 'rehearsal'
            ? Rehearsal::query()->findOrFail($this->eventId)
            : Activity::query()->findOrFail($this->eventId);
    }

    public function isFinalized(): bool
    {
        return $this->selectedEvent?->attendance_finalized_at !== null;
    }

    #[Computed]
    public function statusOptions(): array
    {
        return collect(ParticipationStatus::options())->only(['present', 'late', 'absent', 'excuse'])->all();
    }

    public function render(): View
    {
        return view('livewire.attendance.sheet');
    }

    private function validateRows(bool $final): void
    {
        $statuses = array_keys($this->statusOptions);
        $this->validate([
            'eventType' => ['required', Rule::in(['rehearsal', 'activity'])],
            'eventId' => ['required', 'integer'],
            'rows' => [$final ? 'required' : 'present', 'array', ...($final ? ['min:1'] : [])],
            'rows.*.member_id' => ['required', 'integer'],
            'rows.*.status' => [$final ? 'required' : 'nullable', Rule::in($statuses)],
            'rows.*.minutes_late' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rows.*.excuse_reason' => ['nullable', 'string', 'max:1000'],
        ], messages: [
            'rows.*.status.required' => 'Indiquez le statut de chaque choriste avant de finaliser.',
            'rows.*.minutes_late.min' => 'Le retard doit être d’au moins une minute.',
        ]);

        $this->assertRosterIntegrity();

        foreach ($this->rows as $index => $row) {
            if ($row['status'] === 'late' && blank($row['minutes_late'])) {
                $this->addError("rows.{$index}.minutes_late", 'Indiquez le nombre de minutes de retard.');
            }
            if ($row['status'] === 'excuse' && blank($row['excuse_reason'])) {
                $this->addError("rows.{$index}.excuse_reason", 'Indiquez brièvement le motif accepté.');
            }
        }
        if ($this->getErrorBag()->isNotEmpty()) {
            throw ValidationException::withMessages($this->getErrorBag()->toArray());
        }
    }

    private function persistRows(): void
    {
        foreach ($this->rows as $row) {
            if ($this->eventType === 'rehearsal') {
                $attendance = RehearsalAttendance::query()->where('rehearsal_id', $this->eventId)->where('member_id', $row['member_id'])->first();
                if ($row['status'] === '') {
                    $attendance?->delete();

                    continue;
                }
                RehearsalAttendance::query()->updateOrCreate(
                    ['rehearsal_id' => $this->eventId, 'member_id' => $row['member_id']],
                    $this->attendanceValues($row),
                );
            } else {
                $participant = ActivityParticipant::query()->where('activity_id', $this->eventId)->where('member_id', $row['member_id'])->firstOrFail();
                $participant->fill($this->attendanceValues($row, $row['status'] ?: ParticipationStatus::Invite->value))->save();
            }
        }
    }

    private function attendanceValues(array $row, ?string $status = null): array
    {
        $value = $status ?? $row['status'];

        return [
            'status' => $value,
            'minutes_late' => $value === 'late' ? (int) $row['minutes_late'] : null,
            'excuse_reason' => $value === 'excuse' ? trim($row['excuse_reason']) : null,
            'checked_at' => now(),
            'recorded_by' => auth()->id(),
        ];
    }

    private function row(Member $member, string $status, mixed $minutes, mixed $reason): array
    {
        return ['member_id' => $member->id, 'name' => $member->full_name, 'status' => $status,
            'minutes_late' => $minutes ? (string) $minutes : '', 'excuse_reason' => (string) ($reason ?? '')];
    }

    private function assertRosterIntegrity(): void
    {
        $submitted = collect($this->rows)->pluck('member_id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $expected = $this->eventType === 'rehearsal'
            ? app(AttendanceRoster::class)->rehearsalMembers(Rehearsal::query()->findOrFail($this->eventId))->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()
            : ActivityParticipant::query()->where('activity_id', $this->eventId)->pluck('member_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($submitted !== $expected) {
            throw ValidationException::withMessages([
                'rows' => 'La liste des participants a changé. Rechargez la fiche avant de continuer.',
            ]);
        }
    }
}
