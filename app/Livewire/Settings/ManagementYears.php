<?php

namespace App\Livewire\Settings;

use App\Models\ManagementYear;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

final class ManagementYears extends Component
{
    public string $type = 'pastorale';
    public string $label = '2026-2027';
    public string $starts_on = '';
    public string $ends_on = '';
    public string $theme = '';
    public bool $is_active = true;
    public ?int $editingId = null;

    public function mount(): void
    {
        Gate::authorize('manage-settings');
    }

    public function updatedType(): void
    {
        $this->fillCivilDates();
    }

    public function updatedLabel(): void
    {
        $this->fillCivilDates();
    }

    private function fillCivilDates(): void
    {
        if ($this->type === 'civile' && preg_match('/^\\d{4}$/', $this->label)) {
            $this->starts_on = $this->label.'-01-01';
            $this->ends_on = $this->label.'-12-31';
            $this->theme = '';
        }
    }

    public function edit(int $id): void
    {
        Gate::authorize('manage-settings');
        $year = ManagementYear::findOrFail($id);
        $this->editingId = $year->id;
        $this->type = $year->type;
        $this->label = $year->label;
        $this->starts_on = $year->starts_on->toDateString();
        $this->ends_on = $year->ends_on->toDateString();
        $this->theme = $year->theme ?? '';
        $this->is_active = $year->is_active;
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'type', 'label', 'starts_on', 'ends_on', 'theme', 'is_active');
        $this->type = 'pastorale';
        $this->label = '2026-2027';
        $this->is_active = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        Gate::authorize('manage-settings');
        $data = ManagementYear::validatedAttributes([
            'type' => $this->type,
            'label' => trim($this->label),
            'starts_on' => $this->starts_on,
            'ends_on' => $this->ends_on,
            'theme' => $this->type === 'pastorale' ? trim($this->theme) : null,
            'is_active' => $this->is_active,
        ]);

        $this->validate([
            'label' => [
                Rule::unique('management_years', 'label')
                    ->where('type', $data['type'])
                    ->where('tenant_id', app(\App\Tenancy\TenantContext::class)->requireId())
                    ->ignore($this->editingId),
            ],
        ]);
        $year = $this->editingId ? ManagementYear::findOrFail($this->editingId) : new ManagementYear;
        $year->fill($data)->save();
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Année enregistrée.');
    }

    public function render()
    {
        return view('livewire.settings.management-years', [
            'years' => ManagementYear::orderByDesc('starts_on')->get(),
        ]);
    }
}
