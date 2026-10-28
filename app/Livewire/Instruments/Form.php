<?php

namespace App\Livewire\Instruments;

use App\Enums\InstrumentCategory;
use App\Enums\InstrumentCondition;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\Member;
use App\Services\InstrumentIdentity;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

class Form extends Component
{
    use InteractsWithModule;
    use WithFileUploads;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    public ?Instrument $instrument = null;

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate]
    public string $category = 'orgue';

    #[Validate('nullable|string|max:100')]
    public ?string $serial_number = null;

    #[Validate('required|integer|min:1|max:999')]
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

    public $image = null;

    #[Validate('boolean')]
    public bool $remove_image = false;

    protected function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(InstrumentCategory::class)],
            'condition' => ['required', Rule::enum(InstrumentCondition::class)],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function mount(?Instrument $instrument = null): void
    {
        $this->authorizeManage();

        $instrument = $instrument?->exists ? $instrument : null;
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

        $previousImage = $this->instrument?->image_path;
        $newImage = null;
        if ($this->image) {
            try {
                $newImage = app(PrivateFiles::class)->store($this->image, app(TenantContext::class)->path('instruments'));
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(['image' => $exception->errors()['file'] ?? 'L’image n’a pas pu être enregistrée.']);
            }
        }

        try {
            $this->instrument = DB::transaction(function () use ($data, $newImage) {
                $entry = $this->instrument ? Instrument::query()->lockForUpdate()->findOrFail($this->instrument->id) : new Instrument;
                $out = $entry->exists ? $entry->movements()->whereIn('type', ['sortie', 'pret'])->whereNull('actual_return_on')->sum('quantity') : 0;
                if ($data['quantity'] < $out) {
                    throw ValidationException::withMessages(['quantity' => 'La quantité ne peut pas être inférieure aux unités encore prêtées.']);
                }
                $entry->fill($data);
                if ($newImage !== null || $this->remove_image) {
                    $entry->image_path = $newImage;
                }
                $entry->is_borrowed = $out > 0;
                $entry->save();
                app(InstrumentIdentity::class)->synchronize($entry->id);

                return $entry;
            }, 3);
        } catch (Throwable $exception) {
            if ($newImage) {
                $this->removeImage($newImage);
            }
            throw $exception;
        }
        if (($newImage !== null || $this->remove_image) && $previousImage && $previousImage !== $newImage) {
            $this->removeImage($previousImage);
        }
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

    private function removeImage(string $path): void
    {
        try {
            if (! app(PrivateFiles::class)->delete($path)) {
                Log::warning('Nettoyage d’une ancienne photo d’instrument à reprendre.');
            }
        } catch (Throwable) {
            Log::warning('Nettoyage d’une ancienne photo d’instrument à reprendre.');
        }
    }
}
