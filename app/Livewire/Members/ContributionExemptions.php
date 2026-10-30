<?php

namespace App\Livewire\Members;

use App\Enums\ContributionStatus;
use App\Models\Contribution;
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

    public bool $clears_prior_arrears = false;

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
        $this->reset('ends_on', 'reason', 'clears_prior_arrears');
        $this->starts_on = today()->toDateString();
        $this->showModal = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'type_code' => ['required', Rule::in(array_keys($this->typeOptions))],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'clears_prior_arrears' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $exemption = ContributionExemption::create([...$data, 'member_id' => $this->member->id, 'approved_by' => auth()->id()]);
        if ($exemption->clears_prior_arrears) {
            Contribution::query()->where('member_id', $this->member->id)->where('type_code', $exemption->type_code)
                ->whereDate('due_on', '<', $exemption->starts_on)->where('paid_amount', '<=', 0)
                ->update(['exemption_id' => $exemption->id, 'due_amount' => 0, 'status' => ContributionStatus::Exoneree->value,
                    'notes' => 'Arriéré annulé par exonération : '.($exemption->reason ?: 'décision de la chorale').'.']);
        }
        app(ContributionSchedule::class)->sync();
        $this->cancel();
        $this->dispatch('toast', type: 'success', message: $exemption->clears_prior_arrears ? 'Exonération enregistrée et arriérés impayés antérieurs remis à zéro.' : 'Exonération enregistrée. Les échéances futures sont recalculées.');
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
