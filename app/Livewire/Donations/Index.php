<?php

namespace App\Livewire\Donations;

use App\Enums\DonorType;
use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Donation;
use App\Models\Member;
use App\Services\FinancialCatalog;
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

    #[Url(as: 'vue', except: '')]
    public string $overview = '';

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
        if (in_array($property, ['overview', 'search', 'donorType', 'donationType', 'year'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'donorType', 'donationType', 'year', 'overview']);
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
            ->when($this->overview === 'montant', fn ($q) => $q->orderByDesc('amount'))
            ->when($this->search !== '', function (Builder $query) {
                $like = '%'.$this->search.'%';

                $query->where(function (Builder $q) use ($like) {
                    $q->whereLike('donor_name', $like)
                        ->orWhereLike('purpose', $like);
                });
            })
            ->when($this->donorType !== '', fn (Builder $query) => $query->where('donor_type', $this->donorType))
            ->when($this->donationType !== '', fn (Builder $query) => $query->where(fn ($q) => $q->where('type_code', $this->donationType)->orWhere(fn ($legacy) => $legacy->whereNull('type_code')->where('donation_type', $this->donationType))))
            ->when($this->year !== '', fn (Builder $query) => $query->whereYear('donated_on', (int) $this->year))
            ->orderByDesc('donated_on')
            ->orderByDesc('id')
            ->paginate(15);
    }

    #[Computed]
    public function editingDonation(): ?Donation
    {
        return $this->editingId ? Donation::findOrFail($this->editingId) : null;
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
        return app(FinancialCatalog::class)->options('donation', includeArchived: true);
    }

    #[Computed]
    public function years(): array
    {
        return CalendarYears::for(new Donation, 'donated_on');
    }

    #[Computed]
    public function stats(): array
    {
        $row = Donation::query()->selectRaw('COUNT(*) AS nombre, COALESCE(SUM(amount), 0) AS total, COALESCE(MAX(amount), 0) AS maximum')->toBase()->first();
        $total = (float) $row->total;
        $count = (int) $row->nombre;
        $parType = Donation::query()->selectRaw('COALESCE(type_code, donation_type) AS nature_code, COUNT(*) as nombre, SUM(amount) as total')->groupByRaw('COALESCE(type_code, donation_type)')->orderByDesc('total')->toBase()->first();

        return ['total' => $total, 'nombre' => $count, 'moyenne' => $count > 0 ? $total / $count : 0, 'max' => (float) $row->maximum, 'type_dominant' => $parType?->nature_code, 'type_dominant_montant' => $parType ? (float) $parType->total : 0.0];
    }

    public function render(): View
    {
        return view('livewire.donations.index');
    }
}
