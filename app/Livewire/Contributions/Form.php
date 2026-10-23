<?php

namespace App\Livewire\Contributions;

use App\Enums\ContributionStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Contribution;
use App\Models\Member;
use App\Services\ReceiptLedger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    public ?Contribution $contribution = null;

    #[Validate('required|exists:members,id')]
    public ?string $member_id = null;

    #[Validate('required|integer|min:2000|max:2100')]
    public string $year = '';

    #[Validate]
    public string $month = '1';

    #[Validate('required|numeric|min:0')]
    public ?string $due_amount = null;

    #[Validate('nullable|numeric|min:0|lte:due_amount')]
    public ?string $paid_amount = null;

    #[Validate('nullable|date')]
    public ?string $paid_on = null;

    #[Validate]
    public string $status = 'due';

    #[Validate('nullable|string|max:1000')]
    public ?string $notes = null;

    #[Validate]
    public string $payment_method = 'especes';

    protected function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'status' => ['required', Rule::enum(ContributionStatus::class)],
            'month' => ['required', 'integer', 'min:1', 'max:12', Rule::unique('contributions', 'month')->where('member_id', $this->member_id)->where('year', $this->year)->ignore($this->contribution)],
        ];
    }

    public function mount(?Contribution $contribution = null): void
    {
        $this->authorizeManage();

        $this->contribution = $contribution;

        if ($contribution) {
            $this->fill([
                'payment_method' => $contribution->receipts()->where('status', 'brouillon')->value('payment_method') ?? 'especes',
                'member_id' => $contribution->member_id,
                'year' => (string) $contribution->year,
                'month' => (string) $contribution->month,
                'due_amount' => $contribution->due_amount,
                'paid_amount' => $contribution->paid_amount,
                'paid_on' => $contribution->paid_on?->format('Y-m-d'),
                'status' => $contribution->status->value,
                'notes' => $contribution->notes,
            ]);
        } else {
            $this->year = (string) now()->year;
            $this->month = (string) now()->month;
            $this->due_amount = '2000';
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'payment_method' => $this->payment_method,
            'member_id' => (int) $this->member_id,
            'year' => (int) $this->year,
            'month' => (int) $this->month,
            'due_amount' => (float) $this->due_amount,
            'paid_amount' => $this->paid_amount !== null && $this->paid_amount !== '' ? (float) $this->paid_amount : 0,
            'paid_on' => $this->paid_on ?: null,
            'status' => $this->status,
            'notes' => $this->notes,
        ];

        if ($this->contribution) {
            $this->contribution = app(ReceiptLedger::class)->saveContribution($this->contribution, $data);
            $message = 'Cotisation mise à jour.';
        } else {
            $this->contribution = app(ReceiptLedger::class)->saveContribution(null, $data);
            $message = 'Cotisation enregistrée.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    /**
     * Le règlement met à jour le statut de la cotisation.
     */
    public function updatedPaidAmount(): void
    {
        if ((float) $this->paid_amount >= (float) $this->due_amount && (float) $this->due_amount > 0) {
            $this->status = ContributionStatus::Payee->value;
            $this->paid_on = $this->paid_on ?: today()->format('Y-m-d');
        }
    }

    #[Computed]
    public function members()
    {
        return Member::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();
    }

    #[Computed]
    public function statusOptions(): array
    {
        return ContributionStatus::options();
    }

    public function render(): View
    {
        return view('livewire.contributions.form');
    }
}
