<div>
    <x-page-header title="Mouvements du matériel" icon="cube"
        :subtitle="'Sorties, prêts, transferts et retours — '.$this->stats['total'].' mouvement(s) enregistré(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau mouvement
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Mouvements" :value="$this->stats['total']" icon="cube" color="slate" :href="route('instruments.movements', []).'#resultats'" />
        <x-stat-card label="En cours" :value="$this->stats['en_cours']" icon="chart" color="blue" :href="route('instruments.movements', ['statut'=>'en_cours']).'#resultats'" />
        <x-stat-card label="Retours en retard" :value="$this->stats['en_retard']" icon="wrench" color="red" :href="route('instruments.movements', ['statut'=>'en_retard']).'#resultats'" />
        <x-stat-card label="Clôturés" :value="$this->stats['clotures']" icon="sparkles" color="green" :href="route('instruments.movements', ['statut'=>'cloture']).'#resultats'" />
    </div>

    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-3">
            <div>
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Instrument, destination, motif…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="type" class="sr-only">Type</label>
                <select id="type" wire:model.live="type"
                    class="form-select">
                    <option value="">Tous les types</option>
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="sr-only">Statut</label>
                <select id="status" wire:model.live="status"
                    class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="en_cours">En cours</option>
                    <option value="cloture">Clôturés</option>
                    <option value="en_retard">En retard</option>
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->type !== '' || $this->status !== '')
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
        <x-data-table id="resultats" :headers="[
            ['label' => 'Date'],
            ['label' => 'Instrument'],
            ['label' => 'Type'],
            ['label' => 'Destination'],
            ['label' => 'Responsable'],
            ['label' => 'Retour'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun mouvement" empty-message="Aucun mouvement ne correspond aux filtres sélectionnés."
            empty-icon="cube">
            @forelse ($this->movements as $movement)
                <tr wire:key="movement-{{ $movement->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $movement->moved_on->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $movement->instrument?->name ?? '—' }}</p>
                        @if ($movement->reason)
                            <p class="mt-0.5 text-xs text-slate-500">{{ $movement->reason }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$movement->type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $movement->destination ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($movement->responsible)
                            {{ $movement->responsible->full_name }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm">
                        @if ($movement->isClosed())
                            <span class="text-emerald-600">Rendu le {{ $movement->actual_return_on->format('d/m/Y') }}</span>
                        @elseif ($movement->isOverdue())
                            <span class="font-medium text-rose-600">En retard</span>
                            <p class="text-xs text-slate-500">prévu le {{ $movement->expected_return_on->format('d/m/Y') }}</p>
                        @elseif ($movement->expected_return_on)
                            <span class="text-slate-600">{{ $movement->expected_return_on->format('d/m/Y') }}</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <div class="flex items-center justify-end gap-1">
                                @unless ($movement->isClosed())
                                    <button type="button" wire:click="close({{ $movement->id }})"
                                        wire:confirm="Clôturer ce mouvement comme rendu ?"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-600"
                                        title="Clôturer le mouvement">
                                        <x-icon name="sparkles" class="h-4 w-4" />
                                    </button>
                                @endunless
                                <x-row-actions edit="edit" :id="$movement->id" remove="delete"
                                    :confirm="'Supprimer ce mouvement ?'" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->movements->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier le mouvement' : 'Nouveau mouvement'"
            subtitle="Tracez la sortie, le prêt ou le retour d'un instrument.">
            <livewire:instruments.movement-form :movement="$this->editingMovement" />
        </x-modal-panel>
    @endif
</div>
