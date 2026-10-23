<?php

namespace App\Livewire\Donations;

use App\Enums\DonationType;
use App\Enums\DonorType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Donation;
use App\Models\Member;
use App\Services\ReceiptLedger;
use App\Support\CalendarYears;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use InteractsWithModule;
    use WithPagination;

    public const MODULE_KEY = UserRole::MODULE_COMPTABILITE;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'type', except: '')]
    public string $donorType = '';

    #[Url(as: 'nature', except: '')]
    public string $donationType = '';

    #[Url(as: 'annee', except: '')]
    public string $year = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->authorizeView();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'donorType', 'donationType', 'year'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'donorType', 'donationType', 'year']);
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorizeManage();

        $this->editingId = null;
        $this->showForm = true;
    }

    public function edit(int $donationId): void
    {
        $this->authorizeManage();

        $this->editingId = $donationId;
        $this->showForm = true;
    }

    #[On('close-form')]
    public function closeForm(): void
    {
        $this->showForm = false;
        $this->editingId = null;
    }

    public function delete(int $donationId): void
    {
        $this->authorizeManage();

        app(ReceiptLedger::class)->deleteDonation($donationId);

        $this->dispatch('toast', type: 'success', message: 'Don supprimé.');
    }

    #[Computed]
    public function donations()
    {
        return Donation::query()
            ->with('member')
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('donor_name', $like)
                        ->orWhereLike('purpose', $like);
                });
            })
            ->when($this->donorType !== '', fn (Builder $query) => $query->where('donor_type', $this->donorType))
            ->when($this->donationType !== '', fn (Builder $query) => $query->where('donation_type', $this->donationType))
            ->when($this->year !== '', fn (Builder $query) => $query->whereYear('donated_on', (int) $this->year))
            ->orderByDesc('donated_on')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function editingDonation(): ?Donation
    {
        return $this->editingId ? Donation::find($this->editingId) : null;
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

    #[Computed]
    public function years(): array
    {
        return CalendarYears::for(new Donation, 'donated_on');
    }

    #[Computed]
    public function stats(): array
    {
        $total = (float) Donation::sum('amount');
        $count = Donation::count();

        $parType = Donation::query()
            ->whereNotNull('donation_type')
            ->selectRaw('donation_type, COUNT(*) as nombre, SUM(amount) as total')
            ->groupBy('donation_type')
            ->orderByDesc('total')
            ->first();

        return [
            'total' => $total,
            'nombre' => $count,
            'moyenne' => $count > 0 ? $total / $count : 0,
            'max' => (float) Donation::max('amount'),
            'type_dominant' => $parType?->donation_type?->value,
            'type_dominant_montant' => $parType ? (float) $parType->total : 0.0,
        ];
    }

    public function render(): View
    {
        return view('livewire.donations.index');
    }
}
