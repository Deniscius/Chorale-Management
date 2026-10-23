<?php

namespace App\Livewire\Instruments;

use App\Enums\InstrumentCondition;
use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\InstrumentMovement;
use App\Models\Member;
use App\Services\InstrumentLoans;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class MovementForm extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    public ?InstrumentMovement $movement = null;

    #[Validate('required|exists:instruments,id')]
    public ?string $instrument_id = null;

    #[Validate('required|integer|min:1')]
    public string $quantity = '1';

    #[Validate]
    public string $type = 'sortie';

    #[Validate('required|date')]
    public ?string $moved_on = null;

    #[Validate('nullable|string|max:255')]
    public ?string $reason = null;

    #[Validate('nullable|string|max:255')]
    public ?string $destination = null;

    #[Validate('nullable|exists:members,id')]
    public ?string $responsible_id = null;

    #[Validate('nullable|date|after_or_equal:moved_on')]
    public ?string $expected_return_on = null;

    #[Validate('nullable|date|after_or_equal:moved_on')]
    public ?string $actual_return_on = null;

    #[Validate]
    public ?string $condition_on_return = null;

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(MovementType::class)],
            'condition_on_return' => ['nullable', Rule::enum(InstrumentCondition::class)],
        ];
    }

    public function mount(?InstrumentMovement $movement = null): void
    {
        $this->authorizeManage();

        $movement = $movement?->exists ? $movement : null;
        $this->movement = $movement;

        if ($movement) {
            $this->fill([
                'instrument_id' => $movement->instrument_id,
                'quantity' => (string) $movement->quantity,
                'type' => $movement->type->value,
                'moved_on' => $movement->moved_on->format('Y-m-d'),
                'reason' => $movement->reason,
                'destination' => $movement->destination,
                'responsible_id' => $movement->responsible_id,
                'expected_return_on' => $movement->expected_return_on?->format('Y-m-d'),
                'actual_return_on' => $movement->actual_return_on?->format('Y-m-d'),
                'condition_on_return' => $movement->condition_on_return?->value,
                'notes' => $movement->notes,
            ]);
        } else {
            $this->moved_on = today()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'instrument_id' => (int) $this->instrument_id,
            'quantity' => (int) $this->quantity,
            'type' => $this->type,
            'moved_on' => $this->moved_on,
            'reason' => $this->reason,
            'destination' => $this->destination,
            'responsible_id' => $this->responsible_id !== null && $this->responsible_id !== '' ? (int) $this->responsible_id : null,
            'expected_return_on' => $this->expected_return_on ?: null,
            'actual_return_on' => $this->actual_return_on ?: null,
            'condition_on_return' => $this->condition_on_return ?: null,
            'notes' => $this->notes,
        ];

        $this->movement = app(InstrumentLoans::class)->save($this->movement, $data);
        $message = 'Mouvement enregistré.';

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function instruments()
    {
        return Instrument::orderBy('name')->get();
    }

    #[Computed]
    public function responsibles()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return MovementType::options();
    }

    #[Computed]
    public function conditionOptions(): array
    {
        return InstrumentCondition::options();
    }

    public function render(): View
    {
        return view('livewire.instruments.movement-form');
    }
}
