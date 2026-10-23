<?php

namespace App\Livewire\Budgets;

use App\Enums\BudgetCategory;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Budget;
use App\Models\Choir;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    public ?Budget $budget = null;

    #[Validate('required|integer|min:2000|max:2100')]
    public string $year = '';

    #[Validate('required')]
    public string $category = 'Fonctionnement';

    #[Validate('nullable|exists:choirs,id')]
    public ?string $choir_id = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $allocated_amount = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $committed_amount = null;

    #[Validate('nullable|numeric|min:0')]
    public ?string $realized_amount = null;

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    public function mount(?Budget $budget = null): void
    {
        $this->authorizeManage();

        $this->budget = $budget;

        if ($budget) {
            $this->fill([
                'year' => (string) $budget->year,
                'category' => $budget->category,
                'choir_id' => $budget->choir_id,
                'allocated_amount' => $budget->allocated_amount,
                'committed_amount' => $budget->committed_amount,
                'realized_amount' => $budget->realized_amount,
                'notes' => $budget->notes,
            ]);
        } else {
            $this->year = (string) now()->year;
            $this->choir_id = Choir::query()->value('id');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'year' => (int) $this->year,
            'category' => $this->category,
            'choir_id' => $this->choir_id !== null && $this->choir_id !== '' ? (int) $this->choir_id : null,
            'allocated_amount' => $this->allocated_amount !== null && $this->allocated_amount !== '' ? (float) $this->allocated_amount : 0,
            'committed_amount' => $this->committed_amount !== null && $this->committed_amount !== '' ? (float) $this->committed_amount : 0,
            'realized_amount' => $this->realized_amount !== null && $this->realized_amount !== '' ? (float) $this->realized_amount : 0,
            'notes' => $this->notes,
        ];

        if ($this->budget) {
            $this->budget->update($data);
            $message = 'Ligne budgétaire mise à jour.';
        } else {
            $this->budget = Budget::create($data);
            $message = 'Ligne budgétaire enregistrée.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function choirs()
    {
        return Choir::orderBy('name')->get();
    }

    #[Computed]
    public function categoryOptions(): array
    {
        return BudgetCategory::options();
    }

    public function render(): View
    {
        return view('livewire.budgets.form');
    }
}
