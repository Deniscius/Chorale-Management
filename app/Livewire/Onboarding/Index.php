<?php

namespace App\Livewire\Onboarding;

use App\Models\AuditEvent;
use App\Models\Member;
use App\Models\MemberOnboardingFollowup;
use App\Services\ManagementYearContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

final class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    #[Locked]
    public ?int $editingId = null;

    public string $status = 'a_accueillir';

    public string $contactedOn = '';

    public string $note = '';

    public function mount(): void
    {
        $this->authorizeFollowup();
    }

    public function hydrate(): void
    {
        $this->authorizeFollowup();
    }

    private function authorizeFollowup(): void
    {
        abort_unless(auth()->user()?->can('manage-onboarding'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'statusFilter'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function members()
    {
        $year = app(ManagementYearContext::class)->selected();

        return Member::query()
            ->with('onboardingFollowup')
            ->search($this->search)
            ->when($year, fn ($query) => $query->whereDate('joined_on', '>=', $year->starts_on)
                ->whereDate('joined_on', '<=', $year->ends_on))
            ->when($this->statusFilter !== '', function ($query): void {
                $this->statusFilter === 'a_accueillir'
                    ? $query->whereDoesntHave('onboardingFollowup', fn ($sub) => $sub->where('status', '!=', 'a_accueillir'))
                    : $query->whereHas('onboardingFollowup', fn ($sub) => $sub->where('status', $this->statusFilter));
            })
            ->orderByDesc('joined_on')->orderByDesc('id')
            ->paginate(15);
    }

    public function edit(int $memberId): void
    {
        $this->authorizeFollowup();
        $member = Member::query()->with('onboardingFollowup')->findOrFail($memberId);
        $followup = $member->onboardingFollowup;
        $this->editingId = $member->id;
        $this->status = $followup?->status ?? 'a_accueillir';
        $this->contactedOn = $followup?->contacted_on?->toDateString() ?? '';
        $this->note = $followup?->note ?? '';
        $this->resetValidation();
    }

    public function cancel(): void
    {
        $this->editingId = null;
        $this->reset('status', 'contactedOn', 'note');
    }

    public function save(): void
    {
        $this->authorizeFollowup();
        abort_unless($this->editingId !== null, 422);
        $this->validate([
            'status' => ['required', 'in:a_accueillir,en_accompagnement,integre'],
            'contactedOn' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function (): void {
            $member = Member::query()->findOrFail($this->editingId);
            $record = MemberOnboardingFollowup::query()->firstOrNew(['member_id' => $member->id]);
            $before = $record->exists ? $record->status : 'a_accueillir';
            $record->fill([
                'status' => $this->status,
                'contacted_on' => $this->contactedOn ?: null,
                'note' => trim($this->note) ?: null,
                'updated_by' => auth()->id(),
            ])->save();

            if ($before !== $this->status) {
                // Only status is audited, never the private follow-up note.
                AuditEvent::create([
                    'actor_id' => auth()->id(),
                    'subject_type' => Member::class,
                    'subject_id' => $member->id,
                    'action' => 'updated',
                    'changes' => ['before' => ['onboarding_status' => $before], 'after' => ['onboarding_status' => $this->status]],
                    'created_at' => now(),
                ]);
            }
        }, 3);
        $this->cancel();
        $this->dispatch('toast', type: 'success', message: 'Accueil et suivi du choriste enregistrés.');
    }

    public function render(): View
    {
        return view('livewire.onboarding.index');
    }
}
