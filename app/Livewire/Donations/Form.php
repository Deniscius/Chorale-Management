<?php

namespace App\Livewire\Donations;

use App\Enums\DonationType;
use App\Enums\DonorType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Livewire\Concerns\SearchesMembers;
use App\Models\Donation;
use App\Models\Member;
use App\Services\FinancialCatalog;
use App\Services\ReceiptLedger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class Form extends Component
{
    use InteractsWithModule;
    use SearchesMembers;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    public ?Donation $donation = null;

    #[Locked]
    public ?string $lastProposedAmount = null;

    #[Validate]
    public string $donor_type = 'paroissien';

    #[Validate]
    public string $donation_type = 'offrande_messe';

    #[Validate('nullable|string|max:255')]
    public ?string $donor_name = null;

    #[Validate('nullable|exists:members,id')]
    public ?string $member_id = null;

    #[Validate('required|numeric|min:0')]
    public ?string $amount = null;

    #[Validate('required|date')]
    public ?string $donated_on = null;

    #[Validate('nullable|string|max:255')]
    public ?string $purpose = null;

    #[Validate('nullable|string|max:2000')]
    public ?string $notes = null;

    #[Validate]
    public string $payment_method = 'especes';

    protected function rules(): array
    {
        return [
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'donor_type' => ['required', Rule::enum(DonorType::class)],
            'donation_type' => ['required', Rule::in(array_keys($this->donationTypeOptions))],
        ];
    }

    public function mount(?Donation $donation = null): void
    {
        $this->authorizeManage();

        $donation = $donation?->exists ? $donation : null;
        $this->donation = $donation;

        if ($donation) {
            $this->fill([
                'payment_method' => $donation->transaction?->payment_method?->value ?? 'especes',
                'donor_type' => $donation->donor_type->value,
                'donation_type' => $donation->type_code ?? $donation->donation_type?->value ?? 'offrande_messe',
                'donor_name' => $donation->donor_name,
                'member_id' => $donation->member_id,
                'amount' => $donation->amount,
                'donated_on' => $donation->donated_on->format('Y-m-d'),
                'purpose' => $donation->purpose,
                'notes' => $donation->notes,
            ]);
        } else {
            $this->donated_on = today()->format('Y-m-d');
            $this->donation_type = array_key_first($this->donationTypeOptions) ?? 'autre';
            $this->updatedDonationType();
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'payment_method' => $this->payment_method,
            'donor_type' => $this->donor_type,
            'type_code' => $this->donation_type,
            'donation_type' => $this->donation_type,
            'donor_name' => $this->donor_type === DonorType::Anonyme->value ? null : ($this->donor_name ?: null),
            'member_id' => $this->member_id !== null && $this->member_id !== '' ? (int) $this->member_id : null,
            'amount' => (float) $this->amount,
            'donated_on' => $this->donated_on,
            'purpose' => $this->purpose ?: null,
            'notes' => $this->notes,
        ];

        if ($this->donation) {
            $this->donation = app(ReceiptLedger::class)->saveDonation($this->donation, $data);
            $message = 'Don mis à jour.';
        } else {
            $this->donation = app(ReceiptLedger::class)->saveDonation(null, $data);
            $message = 'Don enregistré.';
        }

        $this->dispatch('toast', type: 'success', message: $message);
        $this->dispatch('close-form');
    }

    /**
     * Le nom du donateur est porté par le choriste sélectionné.
     */
    public function updatedMemberId(): void
    {
        if (! $this->member_id) {
            return;
        }

        $member = Member::find($this->member_id);

        if ($member) {
            $this->donor_type = DonorType::Choriste->value;
            $this->donor_name = $member->full_name;
        }
    }

    /**
     * Un don anonyme n'a pas de nom de donateur.
     */
    public function updatedDonorType(): void
    {
        if ($this->donor_type === DonorType::Anonyme->value) {
            $this->donor_name = null;
            $this->member_id = null;
        }
    }

    #[Computed]
    public function donorTypeOptions(): array
    {
        return DonorType::options();
    }

    #[Computed]
    public function donationTypeOptions(): array
    {
        return app(FinancialCatalog::class)->options('donation', $this->donation?->type_code ?? $this->donation?->donation_type?->value);
    }

    /**
     * Aide contextuelle sur la nature du don sélectionné.
     */
    #[Computed]
    public function donationTypeDescription(): string
    {
        $base = app(FinancialCatalog::class)->all()->firstWhere('code', $this->donation_type)?->base_type;

        return DonationType::tryFrom($base ?? '')?->description() ?? '';
    }

    public function updatedDonationType(): void
    {
        if (! $this->donation) {
            $amount = app(FinancialCatalog::class)->all()->where('kind', 'donation')->firstWhere('code', $this->donation_type)?->default_amount;
            if (blank($this->amount) || ($this->lastProposedAmount !== null && $this->amount === $this->lastProposedAmount)) {
                $this->amount = $amount;
            }
            $this->lastProposedAmount = $amount;
        }
    }

    public function render(): View
    {
        return view('livewire.donations.form');
    }
}
