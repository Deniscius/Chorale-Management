<?php

namespace App\Livewire\Members;

use App\Models\ContributionExemption;
use App\Models\Member;
use App\Services\ContributionExemptionManager;
use App\Services\FinancialCatalog;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

final class ContributionExemptions extends Component
{
    public Member $member;

    public bool $showModal = false;

    public string $type_code = 'mensuelle';

    public string $scope = 'recurring';

    public ?string $activity_id = null;

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
        $this->reset('ends_on', 'reason', 'clears_prior_arrears', 'activity_id');
        $this->scope = 'recurring';
        $this->starts_on = today()->toDateString();
        $this->showModal = false;
    }

    public function save(): void
    {
        $data = $this->validate([
            'scope' => ['required', Rule::in(['recurring', 'activity'])],
            'type_code' => ['required_if:scope,recurring', 'nullable', Rule::in(array_keys($this->typeOptions))],
            'activity_id' => ['required_if:scope,activity', 'nullable', Rule::exists('activities', 'id')],
            'starts_on' => ['required_if:scope,recurring', 'nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'clears_prior_arrears' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['scope'] === 'activity') {
            $activity = $this->activityContributions->firstWhere('activity_id', (int) $data['activity_id']);
            abort_unless($activity, 403);
            $data['type_code'] = $activity->type_code;
            $data['starts_on'] = $activity->due_on;
            $data['ends_on'] = $activity->due_on;
            $data['clears_prior_arrears'] = false;
        }
        unset($data['scope']);
        [, $updated] = app(ContributionExemptionManager::class)->create([...$data, 'member_id' => $this->member->id, 'approved_by' => auth()->id()]);
        $this->cancel();
        $this->dispatch('toast', type: 'success', message: $updated > 0
            ? "Exonération enregistrée : {$updated} échéance(s) actualisée(s)."
            : 'Exonération enregistrée. Les prochaines échéances seront recalculées.');
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

    #[Computed]
    public function activityContributions()
    {
        return \App\Models\Contribution::query()->with('activity:id,title,starts_at')->where('member_id', $this->member->id)->whereNotNull('activity_id')->where('paid_amount', '<=', 0)->get();
    }

    public function render()
    {
        return view('livewire.members.contribution-exemptions');
    }
}
