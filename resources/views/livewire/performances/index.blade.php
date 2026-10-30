<div>
    <x-page-header title="Célébrations & concerts" icon="sparkles"
        :subtitle="'Agenda des célébrations, concerts et veillées — '.$this->stats['total'].' événement(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvel événement
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Événements" :value="$this->stats['total']" icon="sparkles" color="slate" :href="route('performances.index', []).'#resultats'" />
        <x-stat-card label="À venir" :value="$this->stats['avenir']" icon="calendar" color="blue" :href="route('performances.index', ['periode'=>'avenir']).'#resultats'" />
        <x-stat-card label="Budget cumulé" :value="\App\Support\Money::compact($this->stats['budget'])" icon="banknotes" color="amber" :href="route('performances.index', []).'#resultats'" />
        <x-stat-card label="Recettes escomptées" :value="\App\Support\Money::compact($this->stats['recettes'])" icon="chart" color="green" :href="route('performances.index', []).'#resultats'" />
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
                        placeholder="Titre, lieu, public…"
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

            <div>
                <label for="period" class="sr-only">Période</label>
                <select id="period" wire:model.live="period"
                    class="form-select">
                    <option value="">Toutes les périodes</option>
                    <option value="avenir">À venir</option>
                    <option value="passee">Passées</option>
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->type !== '' || $this->status !== '' || $this->period !== '')
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
            ['label' => 'Événement'],
            ['label' => 'Type'],
            ['label' => 'Date & lieu'],
            ['label' => 'Programme'],
            ['label' => 'Budget'],
            ['label' => 'Statut'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun événement" empty-message="Aucune célébration ne correspond aux filtres sélectionnés."
            empty-icon="sparkles">
            @forelse ($this->performances as $performance)
                <tr wire:key="performance-{{ $performance->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $performance->title }}</p>
                        @if ($performance->audience)
                            <p class="mt-0.5 text-xs text-slate-500">Public : {{ $performance->audience }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$performance->type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        <p>{{ $performance->scheduled_at->translatedFormat('l j F Y') }}</p>
                        <p class="text-xs text-slate-500">{{ $performance->scheduled_at->format('H:i') }}
                            @if ($performance->venue) — {{ $performance->venue }} @endif
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        @if ($performance->works->isNotEmpty())
                            <p class="text-xs text-slate-700">{{ $performance->works->pluck('title')->join(' · ') }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $performance->works->count() }} œuvre(s) · {{ $performance->programDuration() }} min
                            </p>
                        @else
                            <span class="text-sm text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        <p><span class="text-xs text-slate-500">Prévu</span> <x-money :value="$performance->budget" /></p>
                        <p class="mt-1"><span class="text-xs text-slate-500">Engagé</span> <x-money :value="$performance->spent_amount ?? 0" /></p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$performance->status" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <x-row-actions edit="edit" :id="$performance->id" remove="delete"
                                :confirm="'Supprimer l\'événement « '.$performance->title.' » ?'" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->performances->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'événement' : 'Nouvel événement'"
            subtitle="Planifiez la célébration, son programme et son budget.">
            <livewire:performances.form :performance="$this->editingPerformance" />
        </x-modal-panel>
    @endif
</div>
