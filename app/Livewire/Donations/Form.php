<?php

namespace App\Livewire\Donations;

use App\Enums\DonationType;
use App\Enums\DonorType;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Donation;
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

    public ?Donation $donation = null;

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
            'donation_type' => ['required', Rule::enum(DonationType::class)],
        ];
    }

    public function mount(?Donation $donation = null): void
    {
        $this->authorizeManage();

        $this->donation = $donation;

        if ($donation) {
            $this->fill([
                'payment_method' => $donation->transaction?->payment_method?->value ?? 'especes',
                'donor_type' => $donation->donor_type->value,
                'donation_type' => $donation->donation_type?->value ?? 'offrande_messe',
                'donor_name' => $donation->donor_name,
                'member_id' => $donation->member_id,
                'amount' => $donation->amount,
                'donated_on' => $donation->donated_on->format('Y-m-d'),
                'purpose' => $donation->purpose,
                'notes' => $donation->notes,
            ]);
        } else {
            $this->donated_on = today()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorizeManage();

        $this->validate();

        $data = [
            'payment_method' => $this->payment_method,
            'donor_type' => $this->donor_type,
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
    public function donorTypeOptions(): array
    {
        return DonorType::options();
    }

    #[Computed]
    public function donationTypeOptions(): array
    {
        return DonationType::options();
    }

    /**
     * Aide contextuelle sur la nature du don sélectionné.
     */
    #[Computed]
    public function donationTypeDescription(): string
    {
        return DonationType::tryFrom($this->donation_type)?->description() ?? '';
    }

    public function render(): View
    {
        return view('livewire.donations.form');
    }
}
