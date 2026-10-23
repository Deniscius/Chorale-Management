<div>
    <x-page-header title="Activités de la chorale" icon="chart"
        :subtitle="'Répétitions, concerts, sorties et actions — '.$this->stats['total'].' activité(s) enregistrée(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle activité
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Activités" :value="$this->stats['total']" icon="chart" color="slate" />
        <x-stat-card label="Planifiées" :value="$this->stats['planifiees']" icon="calendar" color="blue" />
        <x-stat-card label="Terminées" :value="$this->stats['terminees']" icon="sparkles" color="green" />
        <x-stat-card label="Budget cumulé" :value="\App\Support\Money::compact($this->stats['budget'])" icon="banknotes" color="purple"
            :hint="\App\Support\Money::compact($this->stats['depense']).' dépensés'" />
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
                        placeholder="Titre, description, lieu…"
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
                    <option value="encours">En cours</option>
                    <option value="passees">Passées</option>
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->type !== '' || $this->status !== '' || $this->period !== '' || $this->choirId)
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
            ['label' => 'Date'],
            ['label' => 'Activité'],
            ['label' => 'Type'],
            ['label' => 'Lieu'],
            ['label' => 'Organisateur'],
            ['label' => 'Participants'],
            ['label' => 'Budget'],
            ['label' => 'Statut'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucune activité" empty-message="Aucune activité ne correspond aux filtres sélectionnés."
            empty-icon="chart">
            @forelse ($this->activities as $activity)
                <tr wire:key="activity-{{ $activity->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $activity->starts_at->format('d/m/Y') }}
                        <p class="text-xs text-slate-400">{{ $activity->starts_at->format('H:i') }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $activity->title }}</p>
                        @if ($activity->is_public)
                            <span class="mt-0.5 inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">
                                Publiée
                            </span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$activity->type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $activity->venue ?? '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $activity->organizer?->full_name ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        <button type="button" wire:click="manageParticipants({{ $activity->id }})"
                            class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 transition hover:bg-slate-200">
                            <x-icon name="users" class="h-3.5 w-3.5" />
                            {{ $activity->participants_count }}
                        </button>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        <x-money :value="$activity->budget_allocated" />
                        @if ((float) $activity->budget_spent > 0)
                            <p class="text-xs text-slate-400">
                                {{ \App\Support\Money::compact($activity->budget_spent) }} dépensés
                            </p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$activity->status" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <div class="flex items-center justify-end gap-1">
                                @unless (in_array($activity->status->value, ['terminee', 'annulee'], true))
                                    <button type="button" wire:click="close({{ $activity->id }})"
                                        wire:confirm="Marquer cette activité comme terminée ?"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-600"
                                        title="Marquer comme terminée">
                                        <x-icon name="sparkles" class="h-4 w-4" />
                                    </button>
                                @endunless
                                <x-row-actions edit="edit" :id="$activity->id" remove="delete"
                                    :confirm="'Supprimer l\'activité « '.$activity->title.' » ?'" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->activities->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'activité' : 'Nouvelle activité'"
            subtitle="Planifiez l'activité, son budget et sa commission organisatrice.">
            <livewire:activities.form :activity="$this->editingActivity" />
        </x-modal-panel>
    @endif

    @if ($showParticipants && $this->participantsActivity)
        <x-modal-panel title="Participants de l'activité"
            :subtitle="$this->participantsActivity->title">
            <livewire:activities.participants :activity="$this->participantsActivity" />
        </x-modal-panel>
    @endif
</div>
