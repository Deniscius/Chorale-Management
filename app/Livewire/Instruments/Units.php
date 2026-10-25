<?php

namespace App\Livewire\Instruments;

use App\Enums\UserRole;
use App\Livewire\Concerns\InteractsWithModule;
use App\Models\Instrument;
use App\Models\InstrumentUnit;
use App\Services\InstrumentIdentity;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Units extends Component
{
    use InteractsWithModule, WithPagination;

    public const MODULE_KEY = UserRole::MODULE_INSTRUMENTS;

    #[Url(as: 'reference')]
    public ?int $instrumentId = null;

    #[Url(as: 'q')]
    public string $search = '';

    public bool $retired = false;

    public array $selected = [];

    public int $start = 1;

    public function mount(): void
    {
        $this->authorizeView();
        if ($this->instrumentId) {
            Instrument::findOrFail($this->instrumentId);
        }
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'selected')) {
            $this->validate(['selected' => 'array|max:240', 'selected.*' => 'integer|distinct']);
        }
        if (in_array($property, ['instrumentId', 'search', 'retired'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function generate(): void
    {
        $this->authorizeManage();
        $this->validate(['instrumentId' => 'required|integer|exists:instruments,id']);
        $count = app(InstrumentIdentity::class)->generate($this->instrumentId);
        $this->dispatch('toast', type: 'success', message: $count ? $count.' identifiant(s) attribué(s).' : 'Tous les exemplaires ont déjà leur identifiant.');
    }

    public function retire(int $id, bool $retired): void
    {
        $this->authorizeManage();
        app(InstrumentIdentity::class)->setRetired($id, $retired);
        $this->selected = [];
        $this->dispatch('toast', type: 'success', message: $retired ? 'Article retiré, identifiant conservé.' : 'Article réintégré avec son identifiant d’origine.');
    }

    private function query()
    {
        return InstrumentUnit::whereHas('instrument')->with('instrument:id,name,quantity')
            ->when($this->instrumentId, fn ($q) => $q->where('instrument_id', $this->instrumentId))
            ->when($this->retired, fn ($q) => $q->whereNotNull('retired_at'), fn ($q) => $q->whereNull('retired_at'))
            ->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($q) => $q->whereLike('inventory_code', '%'.trim($this->search).'%')->orWhereHas('instrument', fn ($q) => $q->whereLike('name', '%'.trim($this->search).'%'))));
    }

    public function selectPage(): void
    {
        $this->authorizeView();
        $ids = $this->query()->orderBy('instrument_id')->orderBy('ordinal')->paginate(24)->getCollection()->filter(fn ($u) => ! $u->retired_at)->modelKeys();
        $selected = array_values(array_unique([...$this->selected, ...$ids]));
        if (count($selected) > 240) {
            $this->addError('selected', 'Imprimez la sélection actuelle avant d’ajouter d’autres articles (240 maximum).');

            return;
        }
        $this->selected = $selected;
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function render()
    {
        $reference = $this->instrumentId ? Instrument::withCount(['units as identified_count' => fn ($q) => $q->whereNull('retired_at')])->findOrFail($this->instrumentId) : null;

        return view('livewire.instruments.units', ['units' => $this->query()->orderBy('instrument_id')->orderBy('ordinal')->paginate(24), 'references' => Instrument::orderBy('name')->get(['id', 'name']), 'reference' => $reference]);
    }
}
