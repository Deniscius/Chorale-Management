<div>
    <x-page-header title="Maintenance & accords" icon="wrench"
        :subtitle="'Entretiens, réparations et accords du parc instrumental — '.$this->stats['total'].' intervention(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle intervention
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Interventions" :value="$this->stats['total']" icon="wrench" color="slate" :href="route('instruments.maintenances', []).'#resultats'" />
        <x-stat-card label="Planifiées / en cours" :value="$this->stats['planifiees']" icon="calendar" color="amber" :href="route('instruments.maintenances', ['vue'=>'a_faire']).'#resultats'" />
        <x-stat-card label="Terminées" :value="$this->stats['terminees']" icon="sparkles" color="green" :href="route('instruments.maintenances', ['statut'=>'terminee']).'#resultats'" />
        <x-stat-card label="Coût cumulé" :value="\App\Support\Money::compact($this->stats['cout'])" icon="banknotes" color="purple" :href="route('instruments.maintenances', []).'#resultats'" />
    </div>

    @if ($overview !== '')
        <p class="my-3 text-sm text-slate-600">Sélection issue d’une carte de synthèse. <a class="underline" href="{{ url()->current() }}" wire:navigate>Tout afficher</a></p>
    @endif
    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-3">
            <div>
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Instrument, intervention, prestataire…"
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
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
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
            ['label' => 'Instrument'],
            ['label' => 'Type'],
            ['label' => 'Description'],
            ['label' => 'Prestataire'],
            ['label' => 'Dates'],
            ['label' => 'Coût'],
            ['label' => 'Statut'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucune intervention" empty-message="Aucune maintenance ne correspond aux filtres sélectionnés."
            empty-icon="wrench">
            @forelse ($this->maintenances as $maintenance)
                <tr wire:key="maintenance-{{ $maintenance->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                        {{ $maintenance->instrument?->name ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$maintenance->type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ \Illuminate\Support\Str::limit($maintenance->description, 60) ?: '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $maintenance->provider ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600">
                        @if ($maintenance->performed_on)
                            <p>Effectué le {{ $maintenance->performed_on->format('d/m/Y') }}</p>
                        @endif
                        @if ($maintenance->scheduled_for)
                            <p class="text-slate-500">Prévu le {{ $maintenance->scheduled_for->format('d/m/Y') }}</p>
                        @endif
                        @if (! $maintenance->performed_on && ! $maintenance->scheduled_for)
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        <x-money :value="$maintenance->cost" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$maintenance->status" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <x-row-actions edit="edit" :id="$maintenance->id" remove="delete"
                                :confirm="'Supprimer cette intervention ?'" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->maintenances->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'intervention' : 'Nouvelle intervention'"
            subtitle="Accords, réparations et entretiens du parc instrumental.">
            <livewire:instruments.maintenance-form :maintenance="$this->editingMaintenance" />
        </x-modal-panel>
    @endif
</div>
