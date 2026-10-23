<?php

namespace App\Livewire\Instruments;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\InstrumentMaintenance;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class MaintenanceForm extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    public ?InstrumentMaintenance $maintenance = null;

    #[Validate('required|exists:instruments,id')]
    public ?string $instrument_id = null;

    #[Validate]
    public string $type = 'entretien';

    #[Validate('nullable|string|max:1000')]
    public ?string $description = null;

    #[Validate('nullable|string|max:255')]
    public ?string $provider = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $cost = null;

    #[Validate('nullable|date')]
    public ?string $performed_on = null;

    #[Validate('nullable|date')]
    public ?string $scheduled_for = null;

    #[Validate]
    public string $status = 'planifiee';

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MaintenanceType::class)],
            'status' => ['required', Rule::enum(MaintenanceStatus::class)],
        ];
    }

    public function mount(?InstrumentMaintenance $maintenance = null): void
    {
        $this->authorizeManage();

        $maintenance = $maintenance?->exists ? $maintenance : null;
        $this->maintenance = $maintenance;

        if ($maintenance) {
            $this->fill([
                'instrument_id' => $maintenance->instrument_id,
                'type' => $maintenance->type->value,
                'description' => $maintenance->description,
                'provider' => $maintenance->provider,
                'cost' => $maintenance->cost,
                'performed_on' => $maintenance->performed_on?->format('Y-m-d'),
                'scheduled_for' => $maintenance->scheduled_for?->format('Y-m-d'),
                'status' => $maintenance->status->value,
                'notes' => $maintenance->notes,
            ]);
        } else {
            $this->scheduled_for = today()->addWeek()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'instrument_id' => (int) $this->instrument_id,
            'type' => $this->type,
            'description' => $this->description,
            'provider' => $this->provider,
            'cost' => $this->cost !== null && $this->cost !== '' ? (float) $this->cost : 0,
            'performed_on' => $this->performed_on ?: null,
            'scheduled_for' => $this->scheduled_for ?: null,
            'status' => $this->status,
            'notes' => $this->notes,
        ];

        if ($this->maintenance) {
            $this->maintenance->update($data);
            $message = 'Intervention mise à jour.';
        } else {
            $this->maintenance = InstrumentMaintenance::create($data + ['created_by' => auth()->id()]);
            $message = 'Intervention enregistrée.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function instruments()
    {
        return Instrument::orderBy('name')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return MaintenanceType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return MaintenanceStatus::options();
    }

    public function render(): View
    {
        return view('livewire.instruments.maintenance-form');
    }
}
