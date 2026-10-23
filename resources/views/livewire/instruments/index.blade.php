<div>
    <x-page-header title="Inventaire des instruments" icon="cube"
        :subtitle="'Parc instrumental de la chorale — '.$this->stats['total'].' référence(s) pour '.$this->stats['quantite'].' pièce(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvel instrument
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Références" :value="$this->stats['total']" icon="cube" color="slate" />
        <x-stat-card label="Pièces" :value="$this->stats['quantite']" icon="folder" color="purple" />
        <x-stat-card label="Valeur du parc" :value="\App\Support\Money::compact($this->stats['valeur'])" icon="banknotes" color="green" />
        <x-stat-card label="Hors service" :value="$this->stats['horsservice']" icon="wrench" color="red"
            :hint="$this->stats['sortis'].' instrument(s) sorti(s)'" />
    </div>

    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-4">
            <div>
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Nom, numéro de série, emplacement…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="category" class="sr-only">Catégorie</label>
                <select id="category" wire:model.live="category"
                    class="form-select">
                    <option value="">Toutes les catégories</option>
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="condition" class="sr-only">État</label>
                <select id="condition" wire:model.live="condition"
                    class="form-select">
                    <option value="">Tous les états</option>
                    @foreach ($this->conditionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="borrowed" class="sr-only">Disponibilité</label>
                <select id="borrowed" wire:model.live="borrowed"
                    class="form-select">
                    <option value="">Disponibilité</option>
                    <option value="non">Disponible</option>
                    <option value="oui">Sorti / prêté</option>
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->category !== '' || $this->condition !== '' || $this->borrowed !== '')
            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-xs text-slate-500">Filtres actifs</span>
                <button type="button" wire:click="resetFilters"
                    class="btn btn-soft btn-sm">
                    Tout réinitialiser
                </button>
            </div>
        @endif
    </x-filter-bar>

    <div class="mt-4">
        <x-data-table :headers="[
            ['label' => 'Instrument'],
            ['label' => 'Catégorie'],
            ['label' => 'État'],
            ['label' => 'Emplacement'],
            ['label' => 'Responsable'],
            ['label' => 'Valeur'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun instrument" empty-message="Enregistrez le parc instrumental de la commission."
            empty-icon="cube">
            @forelse ($this->instruments as $instrument)
                <tr wire:key="instrument-{{ $instrument->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $instrument->name }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            @if ($instrument->serial_number) N° {{ $instrument->serial_number }} @endif
                            · {{ $instrument->availableQuantity() }} / {{ $instrument->quantity }} unité(s) disponible(s)
                        </p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$instrument->category" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$instrument->condition" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $instrument->location ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($instrument->custodian)
                            {{ $instrument->custodian->full_name }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        <x-money :value="$instrument->current_value" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <x-row-actions edit="edit" :id="$instrument->id" remove="delete"
                                :confirm="'Retirer « '.$instrument->name.' » de l\'inventaire ?'" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->instruments->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'instrument' : 'Nouvel instrument'"
            subtitle="Renseignez la fiche d'inventaire et le responsable de l'instrument.">
            <livewire:instruments.form :instrument="$this->editingInstrument" />
        </x-modal-panel>
    @endif
</div>
