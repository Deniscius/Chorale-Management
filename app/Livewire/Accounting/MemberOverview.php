<?php

namespace App\Livewire\Accounting;

use App\Enums\Voice;
use App\Models\AttendanceIncident;
use App\Models\Contribution;
use App\Models\Member;
use App\Services\ManagementYearContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

final class MemberOverview extends Component
{
    use WithPagination;

    #[Url(as: 'choriste', except: '')]
    public string $memberId = '';

    #[Url(as: 'pupitre', except: '')]
    public string $voice = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function mount(): void
    {
        Gate::authorize('view-attendance-finance');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['memberId', 'voice', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['memberId', 'voice', 'search']);
        $this->resetPage();
    }

    #[Computed]
    public function rows()
    {
        $year = app(ManagementYearContext::class)->selected();

        return $this->memberQuery()
            ->withSum(['contributions as contribution_due' => fn (Builder $query) => $query
                ->accrued()
                ->when($year, fn (Builder $q) => $q->whereDate('period_starts_on', '>=', $year->starts_on)
                    ->whereDate('period_starts_on', '<=', $year->ends_on))], 'due_amount')
            ->withSum(['contributions as contribution_paid' => fn (Builder $query) => $query
                ->accrued()
                ->when($year, fn (Builder $q) => $q->whereDate('period_starts_on', '>=', $year->starts_on)
                    ->whereDate('period_starts_on', '<=', $year->ends_on))], 'paid_amount')
            ->withSum(['attendanceIncidents as incident_amount' => fn (Builder $query) => $query->where('status', AttendanceIncident::PENDING)
                    ->when($year, fn (Builder $q) => $q->whereDate('occurred_on', '>=', $year->starts_on)->whereDate('occurred_on', '<=', $year->ends_on))], 'amount')
            ->withCount([
                'rehearsalAttendances as rehearsal_absences' => fn (Builder $query) => $query->where('status', 'absent')->when($year, fn (Builder $q) => $q->whereHas('rehearsal', fn (Builder $r) => $r->whereDate('date', '>=', $year->starts_on)->whereDate('date', '<=', $year->ends_on))),
                'rehearsalAttendances as rehearsal_lates' => fn (Builder $query) => $query->where('status', 'late')->when($year, fn (Builder $q) => $q->whereHas('rehearsal', fn (Builder $r) => $r->whereDate('date', '>=', $year->starts_on)->whereDate('date', '<=', $year->ends_on))),
                'activityParticipants as activity_absences' => fn (Builder $query) => $query->where('status', 'absent')->when($year, fn (Builder $q) => $q->whereHas('activity', fn (Builder $a) => $a->whereDate('starts_at', '>=', $year->starts_on)->whereDate('starts_at', '<=', $year->ends_on))),
                'activityParticipants as activity_lates' => fn (Builder $query) => $query->where('status', 'late')->when($year, fn (Builder $q) => $q->whereHas('activity', fn (Builder $a) => $a->whereDate('starts_at', '>=', $year->starts_on)->whereDate('starts_at', '<=', $year->ends_on))),
            ])
            ->orderBy('last_name')->orderBy('first_name')->paginate(20);
    }

    #[Computed]
    public function members()
    {
        return Member::query()->orderBy('last_name')->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
    }

    #[Computed]
    public function totals(): array
    {
        $memberIds = $this->memberQuery()->select('id');
        $year = app(ManagementYearContext::class)->selected();
        $contributions = Contribution::query()->accrued()->whereIn('member_id', clone $memberIds)
            ->when($year, fn (Builder $q) => $q->whereDate('period_starts_on', '>=', $year->starts_on)
                ->whereDate('period_starts_on', '<=', $year->ends_on))
            ->selectRaw('COALESCE(SUM(due_amount), 0) AS due, COALESCE(SUM(paid_amount), 0) AS paid')->toBase()->first();
        $incidents = AttendanceIncident::query()->whereIn('member_id', clone $memberIds)
            ->where('status', AttendanceIncident::PENDING)
            ->when($year, fn (Builder $q) => $q->whereDate('occurred_on', '>=', $year->starts_on)->whereDate('occurred_on', '<=', $year->ends_on))
            ->selectRaw("COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount, COALESCE(SUM(CASE WHEN incident_type = 'absence' THEN 1 ELSE 0 END), 0) AS absences, COALESCE(SUM(CASE WHEN incident_type = 'late' THEN 1 ELSE 0 END), 0) AS lates")
            ->toBase()->first();

        return [
            'members' => $this->memberQuery()->count(),
            'due' => (float) $contributions->due,
            'paid' => (float) $contributions->paid,
            'balance' => max(0, (float) $contributions->due - (float) $contributions->paid),
            'incidents' => (int) $incidents->count,
            'incident_amount' => (float) $incidents->amount,
            'absences' => (int) $incidents->absences,
            'lates' => (int) $incidents->lates,
        ];
    }

    #[Computed]
    public function voiceOptions(): array
    {
        return Voice::options();
    }

    private function memberQuery(): Builder
    {
        return Member::query()
            ->when($this->memberId !== '', fn (Builder $query) => $query->whereKey((int) $this->memberId))
            ->when($this->voice !== '', fn (Builder $query) => $query->where('voice', $this->voice))
            ->search($this->search);
    }

    public function render(): View
    {
        return view('livewire.accounting.member-overview');
    }
}
