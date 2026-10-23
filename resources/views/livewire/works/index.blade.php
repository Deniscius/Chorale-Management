<div>
    <x-page-header title="Œuvres & partitions" icon="book"
        :subtitle="'Répertoire, œuvres et supports — '.$this->stats['total'].' œuvre(s) enregistrée(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle œuvre
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Œuvres au catalogue" :value="$this->stats['total']" icon="book" color="slate" />
        <x-stat-card label="Au répertoire" :value="$this->stats['repertoire']" icon="music" color="purple"
            hint="œuvres chantées régulièrement" />
        <x-stat-card label="Partitions disponibles" :value="$this->stats['partitions']" icon="folder" color="green"
            hint="fichiers PDF référencés" />
        <x-stat-card label="Durée totale" :value="$this->stats['duree'].' min'" icon="calendar" color="amber" />
    </div>

    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-5">
            <div class="md:col-span-2">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Titre, compositeur, arrangeur…"
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
                <label for="seasonId" class="sr-only">Temps liturgique</label>
                <select id="seasonId" wire:model.live="seasonId"
                    class="form-select">
                    <option value="">Tous les temps</option>
                    @foreach ($this->seasons as $season)
                        <option value="{{ $season->id }}">{{ $season->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <select id="difficulty" wire:model.live="difficulty"
                    class="form-select">
                    <option value="">Niveau</option>
                    @foreach ($this->difficultyOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>

                <select id="repertoire" wire:model.live="repertoire"
                    class="form-select">
                    <option value="">Répertoire</option>
                    <option value="oui">Oui</option>
                    <option value="non">Non</option>
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->type !== '' || $this->seasonId || $this->difficulty !== '' || $this->repertoire !== '')
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
            ['label' => 'Œuvre'],
            ['label' => 'Type'],
            ['label' => 'Auteur'],
            ['label' => 'Temps liturgique'],
            ['label' => 'Niveau'],
            ['label' => 'Supports'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucune œuvre trouvée"
            empty-message="Modifiez vos filtres ou enregistrez une première œuvre au catalogue."
            empty-icon="book">
            @forelse ($this->works as $work)
                <tr wire:key="work-{{ $work->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $work->title }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">
                            @if ($work->key_signature) {{ $work->key_signature }} @endif
                            @if ($work->mode) · {{ $work->mode }} @endif
                            @if ($work->duration_minutes) · {{ $work->duration_minutes }} min @endif
                            @if ($work->tempo) · ♩ = {{ $work->tempo }} @endif
                        </p>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$work->type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($work->composer)
                            <p>{{ $work->composer }}</p>
                            @if ($work->arranger)
                                <p class="text-xs text-slate-500">arr. {{ $work->arranger }}</p>
                            @endif
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        @if ($work->season)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full bg-{{ $work->season->color }}-500"></span>
                                {{ $work->season->name }}
                            </span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <span class="text-xs font-medium text-slate-600">{{ $work->difficulty->label() }}</span>
                        <span class="ml-1 text-xs text-amber-500">{{ $work->difficulty->stars() }}</span>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <div class="flex items-center gap-1.5">
                            @if ($work->is_repertoire)
                                <span title="Au répertoire"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-purple-100 text-purple-600">
                                    <x-icon name="music" class="h-3.5 w-3.5" />
                                </span>
                            @endif
                            @if ($work->hasPartition())
                                <span title="Partition disponible"
                                    class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
                                    <x-icon name="folder" class="h-3.5 w-3.5" />
                                </span>
                            @endif
                            @if (! $work->is_repertoire && ! $work->hasPartition())
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" wire:click="toggleRepertoire({{ $work->id }})"
                                    class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-purple-600"
                                    title="{{ $work->is_repertoire ? 'Retirer du répertoire' : 'Ajouter au répertoire' }}">
                                    <x-icon name="music" class="h-4 w-4" />
                                </button>
                                <x-row-actions edit="edit" :id="$work->id" remove="delete"
                                    :confirm="'Supprimer l\'œuvre « '.$work->title.' » ?'" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->works->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'œuvre' : 'Nouvelle œuvre'"
            subtitle="Renseignez la fiche descriptive de l'œuvre et de ses supports.">
            <livewire:works.form :work="$this->editingWork" />
        </x-modal-panel>
    @endif
</div>
