<div>
    <x-page-header title="Temps liturgiques" icon="calendar"
        :subtitle="'Calendrier liturgique de la chorale — '.$this->stats['total'].' temps(es) référencé(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau temps liturgique
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Temps liturgiques" :value="$this->stats['total']" icon="calendar" color="slate" :href="route('seasons.index', []).'#resultats'" />
        <x-stat-card label="Œuvres rattachées" :value="$this->stats['oeuvres']" icon="book" color="purple" :href="route('works.index', ['vue'=>'saisons']).'#resultats'" />
        <x-stat-card label="En cours aujourd'hui" :value="$this->stats['encours']" icon="sparkles" color="green" :href="route('seasons.index', ['vue'=>'encours']).'#resultats'" />
    </div>

    @if ($overview !== '')
        <p class="my-3 text-sm text-slate-600">Sélection issue d’une carte de synthèse. <a class="underline" href="{{ url()->current() }}" wire:navigate>Tout afficher</a></p>
    @endif
    <x-filter-bar class="mt-6">
        <div>
            <label for="search" class="sr-only">Rechercher</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input id="search" type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher un temps liturgique…"
                    class="form-input form-input-icon pr-3">
            </div>
        </div>
    </x-filter-bar>

    <div class="mt-4">
        <x-data-table id="resultats" :headers="[
            ['label' => 'Temps liturgique'],
            ['label' => 'Période'],
            ['label' => 'Œuvres'],
            ['label' => 'Description'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun temps liturgique" empty-message="Créez les temps liturgiques de l'année."
            empty-icon="calendar">
            @forelse ($this->seasons as $season)
                <tr wire:key="season-{{ $season->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3">
                        <div class="flex items-center gap-2">
                            <span class="h-3 w-3 shrink-0 rounded-full bg-{{ $season->color }}-500"></span>
                            <span class="text-sm font-semibold text-slate-900">{{ $season->name }}</span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        @if ($season->starts_on || $season->ends_on)
                            {{ $season->starts_on?->format('d/m/Y') ?? '—' }}
                            <span class="text-slate-400">→</span>
                            {{ $season->ends_on?->format('d/m/Y') ?? '—' }}
                        @else
                            <span class="text-slate-400">Non délimité</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $season->works_count }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500">
                        {{ \Illuminate\Support\Str::limit($season->description, 70) ?: '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <x-row-actions edit="edit" :id="$season->id" remove="delete"
                                :confirm="'Supprimer le temps liturgique « '.$season->name.' » ?'" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->seasons->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier le temps liturgique' : 'Nouveau temps liturgique'"
            subtitle="Délimitez la période et la couleur liturgique associée.">
            <livewire:seasons.form :season="$this->editingSeason" />
        </x-modal-panel>
    @endif
</div>
