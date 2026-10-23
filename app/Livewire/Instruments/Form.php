<?php

namespace App\Livewire\Instruments;

use App\Enums\InstrumentCategory;
use App\Enums\InstrumentCondition;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    public ?Instrument $instrument = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate]
    public string $category = 'orgue';

    #[Validate('nullable|string|max:100')]
    public ?string $serial_number = null;

    #[Validate('nullable|integer|min:1|max:999')]
    public string $quantity = '1';

    #[Validate]
    public string $condition = 'bon';

    #[Validate('nullable|date')]
    public ?string $purchase_on = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $purchase_price = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $current_value = null;

    #[Validate('nullable|string|max:255')]
    public ?string $location = null;

    #[Validate('nullable|exists:members,id')]
    public ?int $custodian_id = null;

    #[Validate('boolean')]
    public bool $is_borrowed = false;

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(InstrumentCategory::class)],
            'condition' => ['required', Rule::enum(InstrumentCondition::class)],
        ];
    }

    public function mount(?Instrument $instrument = null): void
    {
        $this->authorizeManage();

        $this->instrument = $instrument;

        if ($instrument) {
            $this->fill([
                'name' => $instrument->name,
                'category' => $instrument->category->value,
                'serial_number' => $instrument->serial_number,
                'quantity' => $instrument->quantity,
                'condition' => $instrument->condition->value,
                'purchase_on' => $instrument->purchase_on?->format('Y-m-d'),
                'purchase_price' => $instrument->purchase_price,
                'current_value' => $instrument->current_value,
                'location' => $instrument->location,
                'custodian_id' => $instrument->custodian_id,
                'is_borrowed' => $instrument->is_borrowed,
                'notes' => $instrument->notes,
            ]);
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'name' => $this->name,
            'category' => $this->category,
            'serial_number' => $this->serial_number,
            'quantity' => (int) $this->quantity,
            'condition' => $this->condition,
            'purchase_on' => $this->purchase_on ?: null,
            'purchase_price' => $this->purchase_price !== null && $this->purchase_price !== '' ? (float) $this->purchase_price : 0,
            'current_value' => $this->current_value !== null && $this->current_value !== '' ? (float) $this->current_value : 0,
            'location' => $this->location,
            'custodian_id' => $this->custodian_id,
            'notes' => $this->notes,
        ];

        $this->instrument = DB::transaction(function () use ($data) {
            $entry = $this->instrument ? Instrument::query()->lockForUpdate()->findOrFail($this->instrument->id) : new Instrument;
            $out = $entry->exists ? $entry->movements()->whereIn('type', ['sortie', 'pret'])->whereNull('actual_return_on')->sum('quantity') : 0;
            if ($data['quantity'] < $out) {
                throw ValidationException::withMessages(['quantity' => 'La quantité ne peut pas être inférieure aux unités encore prêtées.']);
            }
            $entry->fill($data);
            $entry->is_borrowed = $out > 0;
            $entry->save();

            return $entry;
        }, 3);
        $message = 'Instrument enregistré.';

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function custodians()
    {
        return Member::query()
            ->where('status', 'actif')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function categoryOptions(): array
    {
        return InstrumentCategory::options();
    }

    #[Computed]
    public function conditionOptions(): array
    {
        return InstrumentCondition::options();
    }

    public function render(): View
    {
        return view('livewire.instruments.form');
    }
}
