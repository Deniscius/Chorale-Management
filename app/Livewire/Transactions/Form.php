<?php

namespace App\Livewire\Transactions;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Livewire\Concerns\SearchesMembers;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Transaction;
use App\Services\TransactionWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;
    use SearchesMembers;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    public ?Transaction $transaction = null;

    #[Validate]
    public string $type = 'recette';

    #[Validate('required|date')]
    public ?string $transaction_date = null;

    #[Validate('required|string|max:255')]
    public string $description = '';

    #[Validate('required|numeric|min:0')]
    public ?string $amount = null;

    #[Validate('nullable|exists:accounts,id')]
    public ?string $account_id = null;

    #[Validate('nullable|exists:budgets,id')]
    public ?string $budget_id = null;

    #[Validate('nullable|string|max:100')]
    public ?string $category = null;

    #[Validate]
    public string $payment_method = 'especes';

    #[Validate]
    public string $status = 'brouillon';

    #[Validate('nullable|exists:members,id')]
    public ?string $member_id = null;

    #[Validate('nullable|exists:activities,id')]
    public ?string $activity_id = null;

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'status' => ['required', Rule::enum(TransactionStatus::class)],
        ];
    }

    public function mount(?Transaction $transaction = null): void
    {
        $this->authorizeManage();

        $transaction = $transaction?->exists ? $transaction : null;
        $this->transaction = $transaction;

        if ($transaction) {
            $this->fill([
                'type' => $transaction->type->value,
                'transaction_date' => $transaction->transaction_date->format('Y-m-d'),
                'description' => $transaction->description,
                'amount' => $transaction->amount,
                'account_id' => $transaction->account_id,
                'budget_id' => $transaction->budget_id,
                'category' => $transaction->category,
                'payment_method' => $transaction->payment_method->value,
                'status' => $transaction->status->value,
                'member_id' => $transaction->member_id,
                'activity_id' => $transaction->activity_id,
                'notes' => $transaction->notes,
            ]);
        } else {
            $this->transaction_date = today()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'type' => $this->type,
            'transaction_date' => $this->transaction_date,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'account_id' => $this->account_id !== null && $this->account_id !== '' ? (int) $this->account_id : null,
            'budget_id' => $this->budget_id !== null && $this->budget_id !== '' ? (int) $this->budget_id : null,
            'category' => $this->category ?: null,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'member_id' => $this->member_id !== null && $this->member_id !== '' ? (int) $this->member_id : null,
            'activity_id' => $this->activity_id !== null && $this->activity_id !== '' ? (int) $this->activity_id : null,
            'notes' => $this->notes,
        ];

        $this->transaction = app(TransactionWriter::class)->save($this->transaction, $data);
        $message = 'Opération enregistrée ('.$this->transaction->reference.').';

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    #[Computed]
    public function accounts()
    {
        return Account::query()->where('is_active', true)->orderBy('code')->get();
    }

    #[Computed]
    public function budgets()
    {
        return Budget::query()->orderByDesc('year')->orderBy('category')->get();
    }

    #[Computed]
    public function activities()
    {
        return Activity::query()->orderByDesc('starts_at')->get();
    }

    #[Computed]
    public function typeOptions(): array
    {
        return TransactionType::options();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return TransactionStatus::options();
    }

    #[Computed]
    public function paymentMethodOptions(): array
    {
        return PaymentMethod::options();
    }

    #[Computed]
    public function categoryOptions(): array
    {
        $categories = Transaction::query()
            ->select('category')
            ->distinct()
            ->whereNotNull('category')
            ->orderBy('category')
            ->pluck('category')
            ->all();

        return array_combine($categories, $categories) ?: [];
    }

    public function render(): View
    {
        return view('livewire.transactions.form');
    }
}
