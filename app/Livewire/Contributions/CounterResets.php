<?php

namespace App\Livewire\Contributions;

use App\Enums\UserRole;
use App\Models\ContributionCounterReset;
use App\Models\FinancialType;
use App\Models\Member;
use App\Services\ContributionArrears;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Attributes\Computed;

final class CounterResets extends Component
{
    public string $method = 'exemption';
    public string $member_id = '';
    public string $type_code = '';
    public string $starts_on = '';
    public string $ends_on = '';
    public string $reason = '';

    public function mount(): void
    {
        Gate::authorize('manage-settings');
    }

    public function save(ContributionArrears $arrears): void
    {
        Gate::authorize('manage-settings');
        $this->validate([
            'method' => ['required', Rule::in(['exemption', 'new_start'])],
            'member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'type_code' => ['nullable', 'string', 'max:100'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required_if:method,exemption', 'nullable', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $member = Member::findOrFail((int) $this->member_id);
        $start = CarbonImmutable::parse($this->starts_on);
        $type = $this->type_code !== '' ? FinancialType::query()
            ->where('kind', 'contribution')->where('code', $this->type_code)->firstOrFail() : null;
        if ($this->method === 'exemption') {
            if (! $type) {
                $this->addError('type_code', 'Choisissez un type de cotisation pour exonérer.');
                return;
            }
            $count = $arrears->exemptUnpaid(
                $member, $start, CarbonImmutable::parse($this->ends_on),
                $type->code, $this->reason
            );
            $message = $count.' échéance(s) exonérée(s). Les paiements sont conservés.';
        } else {
            $arrears->startNewCounter($member, $start, $this->reason, $type?->code);
            $message = 'Nouveau départ enregistré. Les dettes historiques sont conservées.';
        }

        $this->reset('starts_on', 'ends_on', 'reason');
        $this->dispatch('toast', type: 'success', message: $message);
    }

    #[Computed]
    public function selectedMemberArrears(): ?float
    {
        if (! ctype_digit($this->member_id) || ! ($member = Member::find((int) $this->member_id))) {
            return null;
        }
        return app(ContributionArrears::class)->currentArrears($member, $this->type_code ?: null);
    }

    public function render()
    {
        return view('livewire.contributions.counter-resets', [
            'members' => Member::query()->orderBy('last_name')->orderBy('first_name')->get(),
            'types' => FinancialType::query()->where('kind', 'contribution')->orderBy('label')->get(),
            'history' => ContributionCounterReset::query()->with(['member', 'approver'])
                ->latest()->limit(30)->get(),
        ]);
    }
}
