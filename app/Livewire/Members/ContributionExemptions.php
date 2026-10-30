<?php

namespace App\Livewire\Members;

use App\Models\ContributionExemption;
use App\Models\Member;
use App\Services\ContributionSchedule;
use App\Services\FinancialCatalog;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class ContributionExemptions extends Component
{
    public Member $member;

    public bool $showModal = false;

    public string $type_code = 'mensuelle';

    public string $starts_on = '';

    public ?string $ends_on = null;

    public ?string $reason = null;

    public function mount(Member $member): void
    {
        abort_unless(auth()->user()?->canManageModule('comptabilite'), 403);
        $this->member = $member;
        $this->starts_on = today()->toDateString();
    }

    public function create(): void
    {
        $this->showModal = true;
    }

    public function cancel(): void
    {
        $this->resetValidation();
        $this->reset('ends_on', 'reason');
        $this->starts_on = today()->toDateString();
        $this->showModal = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'type_code' => ['required', Rule::in(array_keys($this->typeOptions))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        ContributionExemption::create([...$data, 'member_id' => $this->member->id, 'approved_by' => auth()->id()]);
        app(ContributionSchedule::class)->sync();
        $this->cancel();
        $this->dispatch('toast', type: 'success', message: 'Exonération enregistrée. Les échéances sans règlement sont recalculées.');
    }

    #[Computed]
    public function exemptions()
    {
        return ContributionExemption::query()->where('member_id', $this->member->id)->latest('starts_on')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return app(FinancialCatalog::class)->options('contribution');
    }

    public function render()
    {
        return view('livewire.members.contribution-exemptions');
    }
}
