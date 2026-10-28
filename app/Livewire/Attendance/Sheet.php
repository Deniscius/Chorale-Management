<?php

namespace App\Livewire\Attendance;

use App\Enums\ParticipationStatus;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Services\AttendanceIncidentManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class Sheet extends Component
{
    public string $eventType = 'rehearsal';

    public ?int $eventId = null;

    /** @var array<int, array{member_id:int,name:string,status:string,minutes_late:string,excuse_reason:string}> */
    public array $rows = [];

    public function mount(): void
    {
        Gate::authorize('view-attendance');
    }

    public function updatedEventType(): void
    {
        $this->eventId = null;
        $this->rows = [];
        unset($this->events, $this->selectedEvent);
    }

    public function updatedEventId(): void
    {
        $this->loadSheet();
    }

    public function loadSheet(): void
    {
        Gate::authorize('view-attendance');
        $this->rows = [];
        if (! $this->eventId) {
            return;
        }

        if ($this->eventType === 'rehearsal') {
            $event = Rehearsal::query()->findOrFail($this->eventId);
            $existing = $event->attendances()->get()->keyBy('member_id');
            $members = Member::query()->active()->orderBy('last_name')->orderBy('first_name')->get();
            foreach ($members as $member) {
                $attendance = $existing->get($member->id);
                $this->rows[] = $this->row($member, $attendance?->status?->value ?? '', $attendance?->minutes_late, $attendance?->excuse_reason);
            }
        } else {
            $event = Activity::query()->findOrFail($this->eventId);
            foreach ($event->participantRecords()->with('member')->get()->sortBy('member.full_name') as $participant) {
                $status = $participant->status === ParticipationStatus::Invite ? '' : $participant->status->value;
                $this->rows[] = $this->row($participant->member, $status, $participant->minutes_late, $participant->excuse_reason);
            }
        }
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
            $this->persistRows();
            if ($this->eventType === 'rehearsal') {
                $event = Rehearsal::query()->lockForUpdate()->findOrFail($this->eventId);
                abort_if($event->attendance_finalized_at !== null, 422, 'Cette fiche est déjà finalisée.');
                $event->forceFill(['attendance_finalized_at' => now(), 'attendance_finalized_by' => auth()->id()])->save();
                app(AttendanceIncidentManager::class)->finalizeRehearsal($event);
            } else {
                $event = Activity::query()->lockForUpdate()->findOrFail($this->eventId);
                abort_if($event->attendance_finalized_at !== null, 422, 'Cette fiche est déjà finalisée.');
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
            ? Rehearsal::query()->orderByDesc('date')->limit(100)->get()
            : Activity::query()->orderByDesc('starts_at')->limit(100)->get();
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
            ? Member::query()->active()->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all()
            : ActivityParticipant::query()->where('activity_id', $this->eventId)->pluck('member_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($submitted !== $expected) {
            throw ValidationException::withMessages([
                'rows' => 'La liste des participants a changé. Rechargez la fiche avant de continuer.',
            ]);
        }
    }
}
