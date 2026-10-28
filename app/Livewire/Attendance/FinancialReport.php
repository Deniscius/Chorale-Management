<?php

namespace App\Livewire\Attendance;

use App\Models\Activity;
use App\Models\AttendanceIncident;
use App\Models\AttendanceRule;
use App\Models\Rehearsal;
use App\Services\Letterhead;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class FinancialReport extends Component
{
    use WithPagination;

    #[Url(as: 'statut', except: 'pending')]
    public string $status = AttendanceIncident::PENDING;

    #[Url(as: 'du', except: '')]
    public string $from = '';

    #[Url(as: 'au', except: '')]
    public string $to = '';

    public bool $financialTrackingEnabled = false;

    public string $rehearsalAbsenceAmount = '0';

    public string $activityAbsenceAmount = '0';

    public string $lateAmount = '0';

    public int $lateThresholdMinutes = 1;

    public string $effectiveFrom = '';

    public function mount(): void
    {
        Gate::authorize('view-attendance-finance');
        $rule = AttendanceRule::query()->firstOrCreate([]);
        $this->financialTrackingEnabled = $rule->financial_tracking_enabled;
        $this->rehearsalAbsenceAmount = (string) $rule->rehearsal_absence_amount;
        $this->activityAbsenceAmount = (string) $rule->activity_absence_amount;
        $this->lateAmount = (string) $rule->late_amount;
        $this->lateThresholdMinutes = $rule->late_threshold_minutes;
        $this->effectiveFrom = $rule->effective_from?->toDateString() ?? '';
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'from', 'to'], true)) {
            $this->resetPage();
        }
    }

    public function saveRules(): void
    {
        Gate::authorize('configure-attendance-finance');
        $validated = $this->validate([
            'financialTrackingEnabled' => ['boolean'],
            'rehearsalAbsenceAmount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'activityAbsenceAmount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'lateAmount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'lateThresholdMinutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'effectiveFrom' => ['nullable', 'date'],
        ]);

        AttendanceRule::query()->firstOrCreate([])->update([
            'financial_tracking_enabled' => $validated['financialTrackingEnabled'],
            'rehearsal_absence_amount' => $validated['rehearsalAbsenceAmount'],
            'activity_absence_amount' => $validated['activityAbsenceAmount'],
            'late_amount' => $validated['lateAmount'],
            'late_threshold_minutes' => $validated['lateThresholdMinutes'],
            'effective_from' => $validated['effectiveFrom'] ?: null,
            'updated_by' => auth()->id(),
        ]);
        $this->dispatch('toast', type: 'success', message: 'Règles enregistrées. Elles s’appliqueront aux prochaines fiches finalisées.');
    }

    public function waive(int $incidentId, string $note = ''): void
    {
        Gate::authorize('manage-attendance-finance');
        $incident = AttendanceIncident::query()->findOrFail($incidentId);
        abort_unless($incident->status === AttendanceIncident::PENDING, 422);
        $incident->update(['status' => AttendanceIncident::WAIVED, 'reviewed_by' => auth()->id(),
            'reviewed_at' => now(), 'review_note' => trim($note) ?: 'Dispense décidée par la comptabilité.']);
        $this->dispatch('toast', type: 'success', message: 'Incident dispensé et conservé dans l’historique.');
    }

    public function restore(int $incidentId): void
    {
        Gate::authorize('manage-attendance-finance');
        $incident = AttendanceIncident::query()->findOrFail($incidentId);
        abort_unless($incident->status === AttendanceIncident::WAIVED, 422);
        $incident->update(['status' => AttendanceIncident::PENDING, 'reviewed_by' => auth()->id(),
            'reviewed_at' => now(), 'review_note' => 'Dispense annulée.']);
        $this->dispatch('toast', type: 'success', message: 'Incident réintégré au rapport.');
    }

    #[Computed]
    public function incidents()
    {
        return $this->query()->with('member')->orderByDesc('occurred_on')->orderByDesc('id')->paginate(20);
    }

    /** Toutes les fiches finalisées restent visibles, même sans incident financier. */
    #[Computed]
    public function finalizedSheets()
    {
        $rehearsals = Rehearsal::query()
            ->whereNotNull('attendance_finalized_at')
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('date', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('date', '<=', $this->to))
            ->with('attendanceFinalizer:id,name')
            ->withCount([
                'attendances as attendance_total',
                'attendances as present_count' => fn (Builder $query) => $query->where('status', 'present'),
                'attendances as late_count' => fn (Builder $query) => $query->where('status', 'late'),
                'attendances as absent_count' => fn (Builder $query) => $query->where('status', 'absent'),
                'attendances as excused_count' => fn (Builder $query) => $query->where('status', 'excuse'),
            ])->latest('date')->limit(100)->get()->map(fn (Rehearsal $event): array => [
                'type' => 'repetition', 'id' => $event->id, 'date' => $event->date,
                'label' => $event->venue ? 'Répétition — '.$event->venue : 'Répétition',
                'finalized_at' => $event->attendance_finalized_at, 'finalizer' => $event->attendanceFinalizer?->name,
                'total' => $event->attendance_total, 'present' => $event->present_count,
                'late' => $event->late_count, 'absent' => $event->absent_count, 'excused' => $event->excused_count,
            ]);

        $activities = Activity::query()
            ->whereNotNull('attendance_finalized_at')
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('starts_at', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('starts_at', '<=', $this->to))
            ->with('attendanceFinalizer:id,name')
            ->withCount([
                'participantRecords as attendance_total',
                'participantRecords as present_count' => fn (Builder $query) => $query->where('status', 'present'),
                'participantRecords as late_count' => fn (Builder $query) => $query->where('status', 'late'),
                'participantRecords as absent_count' => fn (Builder $query) => $query->where('status', 'absent'),
                'participantRecords as excused_count' => fn (Builder $query) => $query->where('status', 'excuse'),
            ])->latest('starts_at')->limit(100)->get()->map(fn (Activity $event): array => [
                'type' => 'activite', 'id' => $event->id, 'date' => $event->starts_at,
                'label' => $event->title, 'finalized_at' => $event->attendance_finalized_at,
                'finalizer' => $event->attendanceFinalizer?->name, 'total' => $event->attendance_total,
                'present' => $event->present_count, 'late' => $event->late_count,
                'absent' => $event->absent_count, 'excused' => $event->excused_count,
            ]);

        return $rehearsals->concat($activities)->sortByDesc('date')->values();
    }

    #[Computed]
    public function totals(): array
    {
        $query = $this->query();

        return [
            'count' => (clone $query)->count(),
            'amount' => (float) (clone $query)->sum('amount'),
            'absences' => (clone $query)->where('incident_type', 'absence')->count(),
            'late' => (clone $query)->where('incident_type', 'late')->count(),
        ];
    }

    private function query(): Builder
    {
        return AttendanceIncident::query()
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->from !== '', fn (Builder $query) => $query->whereDate('occurred_on', '>=', $this->from))
            ->when($this->to !== '', fn (Builder $query) => $query->whereDate('occurred_on', '<=', $this->to));
    }

    public function render(): View
    {
        return view('livewire.attendance.financial-report', [
            'activeLetterhead' => app(Letterhead::class)->active(),
        ]);
    }
}
