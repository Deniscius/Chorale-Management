<div>
    <x-page-header title="Répétitions" icon="music"
        :subtitle="'Planning des répétitions et œuvres au programme — '.$this->stats['total'].' séance(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle répétition
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Séances planifiées" :value="$this->stats['total']" icon="calendar" color="slate" :href="route('rehearsals.index', []).'#resultats'" />
        <x-stat-card label="À venir" :value="$this->stats['avenir']" icon="chart" color="blue" :href="route('rehearsals.index', ['periode'=>'avenir']).'#resultats'" />
        <x-stat-card label="Séances terminées" :value="$this->stats['terminees']" icon="sparkles" color="green" :href="route('rehearsals.index', ['statut'=>'terminee']).'#resultats'" />
        <x-stat-card label="Présences enregistrées" :value="$this->stats['presence']" icon="users" color="purple" :href="route('rehearsals.index', ['vue'=>'presences']).'#resultats'" />
    </div>

    @if ($overview !== '')
        <p class="mt-4 text-sm text-slate-600">Séances avec des présences enregistrées. <a href="{{ route('rehearsals.index') }}" wire:navigate class="underline">Tout afficher</a></p>
    @endif
    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-4">
            <div>
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Lieu, notes…"
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
            ['label' => 'Date'],
            ['label' => 'Type'],
            ['label' => 'Lieu & horaire'],
            ['label' => 'Programme'],
            ['label' => 'Présences'],
            ['label' => 'Statut'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucune répétition" empty-message="Aucune séance ne correspond aux filtres sélectionnés."
            empty-icon="music">
            @forelse ($this->rehearsals as $rehearsal)
                <tr wire:key="rehearsal-{{ $rehearsal->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg bg-slate-100 text-center">
                                <span class="text-sm font-bold leading-none text-slate-900">{{ $rehearsal->date->format('d') }}</span>
                                <span class="mt-0.5 text-[10px] font-semibold uppercase text-slate-500">{{ $rehearsal->date->translatedFormat('M') }}</span>
                            </span>
                            <span class="text-xs text-slate-500">{{ $rehearsal->date->translatedFormat('Y') }}</span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$rehearsal->type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($rehearsal->venue)
                            <p>{{ $rehearsal->venue }}</p>
                        @endif
                        @if ($rehearsal->timeRange())
                            <p class="text-xs text-slate-500">{{ $rehearsal->timeRange() }}</p>
                        @endif
                        @if (! $rehearsal->venue && ! $rehearsal->timeRange())
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($rehearsal->works->isNotEmpty())
                            <p class="text-xs text-slate-700">{{ $rehearsal->works->pluck('title')->join(' · ') }}</p>
                            <p class="mt-0.5 text-xs text-slate-500">{{ $rehearsal->works->count() }} œuvre(s)</p>
                        @else
                            <span class="text-sm text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $rehearsal->attendances_count }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$rehearsal->status" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <div class="flex items-center justify-end gap-1">
                                @if ($this->aiEnabled)
                                    <button type="button" wire:click="openReport({{ $rehearsal->id }})"
                                        class="btn btn-soft btn-xs" title="Compte rendu assisté">
                                        <x-icon name="robot" class="h-3.5 w-3.5" />
                                        Compte rendu
                                    </button>
                                @endif

                                <x-row-actions edit="edit" :id="$rehearsal->id" remove="delete"
                                    :confirm="'Supprimer la répétition du '.$rehearsal->date->format('d/m/Y').' ?'" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->rehearsals->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier la répétition' : 'Nouvelle répétition'"
            subtitle="Planifiez la séance et composez le programme d'œuvres.">
            <livewire:rehearsals.form :rehearsal="$this->editingRehearsal" />
        </x-modal-panel>
    @endif

    @if ($reportId && $this->reportRehearsal)
        <x-modal-panel title="Compte rendu assisté" size="lg"
            subtitle="Proposition rédigée à partir de la séance : relisez et corrigez avant d’enregistrer.">
            <div class="space-y-4">
                <p class="text-sm text-slate-600">Les notes libres sont exclues de l’envoi à l’IA. Vérifiez le contexte avant de générer une proposition.</p>
                <details class="rounded-lg border p-3 text-sm">
                    <summary>Voir les informations transmises à l’IA</summary>
                    <pre class="mt-3 whitespace-pre-wrap">{{ $this->reportContext }}</pre>
                </details>
                <div class="rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <p class="font-medium text-slate-800">
                        {{ $this->reportRehearsal->date->translatedFormat('l j F Y') }}
                        @if ($this->reportRehearsal->venue)
                            — {{ $this->reportRehearsal->venue }}
                        @endif
                    </p>
                    @if ($this->reportRehearsal->ai_report_generated_at)
                        <p class="mt-1 text-xs text-slate-500">
                            Dernière génération : {{ $this->reportRehearsal->ai_report_generated_at->translatedFormat('d M Y · H:i') }}
                        </p>
                    @endif
                </div>

                <div>
                    <x-input-label for="reportDraft" value="Compte rendu" />
                    <textarea id="reportDraft" wire:model="reportDraft" rows="12"
                        class="form-textarea mt-1"
                        placeholder="Cliquez sur « Générer une proposition » pour obtenir un brouillon, ou rédigez directement."></textarea>
                    <x-input-error :messages="$errors->get('reportDraft')" class="mt-1" />
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
                    <button type="button" wire:click="generateReport" wire:loading.attr="disabled"
                        class="btn btn-soft">
                        <span wire:loading.remove wire:target="generateReport">
                            <x-icon name="sparkles" class="h-4 w-4" />
                            Générer une proposition
                        </span>
                        <span wire:loading wire:target="generateReport">Rédaction en cours…</span>
                    </button>

                    <div class="flex items-center gap-3">
                        <button type="button" wire:click="$dispatch('close-form')" class="btn btn-secondary">
                            Fermer
                        </button>
                        <button type="button" wire:click="saveReport" class="btn btn-primary">
                            Enregistrer
                        </button>
                    </div>
                </div>
            </div>
        </x-modal-panel>
    @endif
</div>
