<?php

namespace App\Livewire\Settings;

use App\Enums\DonationType;
use App\Models\FinancialType;
use App\Models\Setting;
use App\Services\FinancialCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
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

    public string $contribution_label = 'Cotisations';

    public string $donation_label = 'Dons';

    public function mount(): void
    {
        $this->authorizeSettings();
        $this->contribution_label = app(FinancialCatalog::class)->section('contribution');
        $this->donation_label = app(FinancialCatalog::class)->section('donation');
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
        $this->validate(['contribution_label' => 'required|string|max:60', 'donation_label' => 'required|string|max:60']);
        DB::transaction(fn () => Setting::setMany(['finance.label.contribution' => trim($this->contribution_label), 'finance.label.donation' => trim($this->donation_label)]));
        $this->redirectRoute('settings.financial-types');
    }

    public function edit(int $id): void
    {
        $this->authorizeSettings();
        $type = FinancialType::findOrFail($id);
        $this->resetValidation();
        $this->editingId = $type->id;
        $this->fill($type->only('kind', 'label', 'default_amount', 'is_active'));
        $this->base_type = $type->base_type ?? 'autre';
    }

    public function cancel(): void
    {
        $this->authorizeSettings();
        $this->reset('editingId', 'kind', 'label', 'default_amount', 'is_active', 'base_type');
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->authorizeSettings();
        $this->label = trim(preg_replace('/\s+/u', ' ', $this->label));
        $this->validate(['kind' => ['required', Rule::in(['contribution', 'donation'])], 'label' => 'required|string|max:80', 'default_amount' => 'nullable|numeric|min:0|max:999999999.99|decimal:0,2', 'is_active' => 'boolean', 'base_type' => ['required', Rule::in(['autre', 'don_materiel', ...array_keys(DonationType::options())])]]);
        try {
            DB::transaction(function () {
                $type = $this->editingId ? FinancialType::lockForUpdate()->findOrFail($this->editingId) : new FinancialType;
                if (! $type->exists) {
                    $type->forceFill(['kind' => $this->kind, 'code' => ($this->kind === 'donation' ? 'don_' : 'cot_').Str::lower(Str::random(20)), 'base_type' => $this->kind === 'donation' ? $this->base_type : null]);
                }
                $type->fill(['label' => $this->label, 'default_amount' => filled($this->default_amount) ? $this->default_amount : null, 'is_active' => $this->is_active]);
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

    public function render()
    {
        return view('livewire.settings.financial-types');
    }
}
