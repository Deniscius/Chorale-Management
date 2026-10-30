<?php

namespace App\Livewire\Settings;

use App\Enums\ContributionFrequency;
use App\Enums\DonationType;
use App\Enums\PerformanceType;
use App\Models\FinancialType;
use App\Models\Setting;
use App\Services\FinancialCatalog;
use App\Services\PerformanceFundingPolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class FinancialTypes extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $kind = 'contribution';

    public string $label = '';

    public ?string $default_amount = null;

    public bool $is_active = true;

    public string $base_type = 'autre';

    public int $frequency_months = 0;

    public ?string $starts_on = null;

    public ?int $due_day = null;

    public bool $showTypeModal = false;

    public bool $showLabelsModal = false;

    public bool $showFundingModal = false;

    /** @var array<string, string|int|float|null> */
    public array $performance_budgets = [];

    public string $contribution_label = 'Cotisations';

    public string $donation_label = 'Dons';

    public string $exercise_mode = 'civil';

    public int $pastoral_start_month = 9;

    public function mount(): void
    {
        $this->authorizeSettings();
        $this->contribution_label = app(FinancialCatalog::class)->section('contribution');
        $this->donation_label = app(FinancialCatalog::class)->section('donation');
        $this->exercise_mode = Setting::get('finance.exercise_mode', 'civil');
        $this->pastoral_start_month = (int) Setting::get('finance.pastoral_start_month', 9);
        $this->performance_budgets = app(PerformanceFundingPolicy::class)->all();
    }

    public function hydrate(): void
    {
        $this->authorizeSettings();
    }

    private function authorizeSettings(): void
    {
        abort_unless(auth()->user()?->can('manage-settings'), 403);
    }

    public function saveLabels(): void
    {
        $this->authorizeSettings();
        $this->validate([
            'contribution_label' => 'required|string|max:60', 'donation_label' => 'required|string|max:60',
            'exercise_mode' => ['required', Rule::in(['civil', 'pastoral'])],
            'pastoral_start_month' => ['required_if:exercise_mode,pastoral', 'integer', 'min:1', 'max:12'],
        ]);
        DB::transaction(fn () => Setting::setMany([
            'finance.label.contribution' => trim($this->contribution_label), 'finance.label.donation' => trim($this->donation_label),
            'finance.exercise_mode' => $this->exercise_mode, 'finance.pastoral_start_month' => $this->pastoral_start_month,
        ]));
        $this->showLabelsModal = false;
        $this->redirectRoute('settings.financial-types');
    }

    public function create(): void
    {
        $this->cancel();
        $this->showTypeModal = true;
    }

    public function openLabels(): void
    {
        $this->showLabelsModal = true;
    }

    public function openFunding(): void
    {
        $this->performance_budgets = app(PerformanceFundingPolicy::class)->all();
        $this->showFundingModal = true;
    }

    #[On('close-labels')]
    public function closeLabels(): void
    {
        $this->showLabelsModal = false;
        $this->resetValidation();
    }

    #[On('close-funding')]
    public function closeFunding(): void
    {
        $this->showFundingModal = false;
        $this->resetValidation();
    }

    public function saveFunding(): void
    {
        $this->authorizeSettings();
        $rules = collect(PerformanceType::cases())->mapWithKeys(fn ($type) => [
            'performance_budgets.'.$type->value => 'nullable|numeric|min:0|max:999999999.99|decimal:0,2',
        ])->all();
        $this->validate($rules);
        $values = collect(PerformanceType::cases())->mapWithKeys(fn ($type) => [
            'finance.performance_budget.'.$type->value => (float) ($this->performance_budgets[$type->value] ?? 0),
        ])->all();
        DB::transaction(fn () => Setting::setMany($values));
        $this->showFundingModal = false;
        $this->dispatch('toast', type: 'success', message: 'Fonds de soutien par célébration enregistrés.');
    }

    public function edit(int $id): void
    {
        $this->authorizeSettings();
        $type = FinancialType::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $type->id;
        $this->fill($type->only('kind', 'label', 'default_amount', 'frequency_months', 'due_day', 'is_active'));
        $this->starts_on = $type->starts_on?->format('Y-m-d');
        $this->base_type = $type->base_type ?? 'autre';
        $this->showTypeModal = true;
    }

    public function cancel(): void
    {
        $this->authorizeSettings();
        $this->reset('editingId', 'kind', 'label', 'default_amount', 'frequency_months', 'starts_on', 'due_day', 'is_active', 'base_type');
        $this->showTypeModal = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeSettings();
        $this->label = trim(preg_replace('/\s+/u', ' ', $this->label));
        $this->validate([
            'kind' => ['required', Rule::in(['contribution', 'donation'])],
            'label' => 'required|string|max:80',
            'default_amount' => 'nullable|numeric|min:0|max:999999999.99|decimal:0,2',
            'frequency_months' => ['required', Rule::in(array_map(fn ($case) => $case->value, ContributionFrequency::cases()))],
            'starts_on' => 'nullable|date',
            'due_day' => 'nullable|required_unless:frequency_months,0|integer|min:1|max:31',
            'is_active' => 'boolean',
            'base_type' => ['required', Rule::in(['autre', 'don_materiel', ...array_keys(DonationType::options())])],
        ]);
        try {
            DB::transaction(function () {
                $type = $this->editingId ? FinancialType::lockForUpdate()->findOrFail($this->editingId) : new FinancialType;
                if (! $type->exists) {
                    $type->forceFill(['kind' => $this->kind, 'code' => ($this->kind === 'donation' ? 'don_' : 'cot_').Str::lower(Str::random(20)), 'base_type' => $this->kind === 'donation' ? $this->base_type : null]);
                }
                $type->fill([
                    'label' => $this->label,
                    'default_amount' => filled($this->default_amount) ? $this->default_amount : null,
                    'frequency_months' => $this->kind === 'contribution' ? $this->frequency_months : 0,
                    'starts_on' => $this->kind === 'contribution' && $this->frequency_months > 0 ? $this->starts_on : null,
                    'due_day' => $this->kind === 'contribution' && $this->frequency_months > 0 ? $this->due_day : null,
                    'is_active' => $this->is_active,
                ]);
                $type->forceFill(['label_key' => mb_strtolower($this->label)])->save();
            });
        } catch (UniqueConstraintViolationException $e) {
            $this->addError('label', 'Un type avec ce nom existe déjà dans cette rubrique.');

            return;
        }
        $this->cancel();
        $this->dispatch('toast', type: 'success', message: 'Type enregistré. Les montants déjà enregistrés sont conservés.');
    }

    #[Computed]
    public function types()
    {
        return FinancialType::orderBy('kind')->orderBy('id')->get();
    }

    #[Computed]
    public function frequencyOptions(): array
    {
        return ContributionFrequency::options();
    }

    #[Computed]
    public function performanceTypeOptions(): array
    {
        return PerformanceType::options();
    }

    public function render()
    {
        return view('livewire.settings.financial-types');
    }
}
