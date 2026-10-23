<?php

namespace App\Livewire\Instruments;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\InstrumentEnsemble;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EnsembleForm extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    public ?InstrumentEnsemble $ensemble = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:2000')]
    public ?string $description = null;

    #[Validate('nullable|exists:members,id')]
    public ?string $conductor_id = null;

    #[Validate('nullable|integer|min:0|max:500')]
    public string $member_count = '0';

    public function mount(?InstrumentEnsemble $ensemble = null): void
    {
        $this->authorizeManage();

        $ensemble = $ensemble?->exists ? $ensemble : null;
        $this->ensemble = $ensemble;

        if ($ensemble) {
            $this->fill([
                'name' => $ensemble->name,
                'description' => $ensemble->description,
                'conductor_id' => $ensemble->conductor_id,
                'member_count' => $ensemble->member_count,
            ]);
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'conductor_id' => $this->conductor_id !== null && $this->conductor_id !== '' ? (int) $this->conductor_id : null,
            'member_count' => (int) $this->member_count,
        ];

        if ($this->ensemble) {
            $this->ensemble->update($data);
            $message = 'Ensemble mis à jour.';
        } else {
            $this->ensemble = InstrumentEnsemble::create($data);
            $message = 'Ensemble enregistré.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function conductors()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    public function render(): View
    {
        return view('livewire.instruments.ensemble-form');
    }
}
