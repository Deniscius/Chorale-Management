<div>
    <x-validation-summary />
    <x-page-header :title="app(\App\Services\FinancialCatalog::class)->section('contribution')" icon="users"
        :subtitle="'Suivi par type, mois et exercice — '.$this->stats['total'].' ligne(s).'">
        <x-slot name="actions">
            @can('manage-settings')<a href="{{ route('settings.financial-types') }}" class="btn btn-secondary">Configurer les types</a>@endcan
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle cotisation
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="app(\App\Services\FinancialCatalog::class)->section('contribution')" :value="$this->stats['total']" icon="users" color="slate" :href="route('contributions.index', []).'#resultats'" />
        <x-stat-card label="Montant dû" :value="\App\Support\Money::compact($this->stats['du'])" icon="banknotes" color="blue" :href="route('contributions.index', []).'#resultats'" />
        <x-stat-card label="Encaissé" :value="\App\Support\Money::compact($this->stats['encaisse'])" icon="sparkles" color="green" :href="route('contributions.index', ['vue'=>'reglees']).'#resultats'" />
        <x-stat-card label="Reste à recouvrer" :value="\App\Support\Money::compact($this->stats['reste'])" icon="folder" color="amber"
            :hint="$this->stats['impayees'].' impayée(s)'" :href="route('contributions.index', ['vue'=>'impayees']).'#resultats'" />
    </div>

    @if ($overview !== '')
        <p class="my-3 text-sm text-slate-600">Sélection issue d’une carte de synthèse. <a class="underline" href="{{ url()->current() }}" wire:navigate>Tout afficher</a></p>
    @endif
    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
            <div><label for="contribution-type-filter" class="sr-only">Type de cotisation</label><select id="contribution-type-filter" wire:model.live="typeCode" class="form-select"><option value="">Tous les types</option>@foreach(app(\App\Services\FinancialCatalog::class)->options('contribution', includeArchived: true) as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
            <div>
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Nom du choriste…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="year" class="sr-only">Exercice</label>
                <select id="year" wire:model.live="year"
                    class="form-select">
                    <option value="">Tous les exercices</option>
                    @foreach ($this->years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="month" class="sr-only">Mois</label>
                <select id="month" wire:model.live="month"
                    class="form-select">
                    <option value="">Tous les mois</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \App\Support\Months::label($m) }}</option>
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

        @if ($this->search !== '' || $this->year !== '' || $this->month !== '' || $this->status !== '' || $this->typeCode !== '')
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
            ['label' => 'Choriste'],
            ['label' => 'Exercice'],
            ['label' => 'Mois'],
            ['label' => 'Dû', 'align' => 'text-right'],
            ['label' => 'Payé', 'align' => 'text-right'],
            ['label' => 'Solde', 'align' => 'text-right'],
            ['label' => 'Statut'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucune cotisation" empty-message="Aucune cotisation ne correspond aux filtres sélectionnés."
            empty-icon="users">
            @forelse ($this->contributions as $contribution)
                <tr wire:key="contribution-{{ $contribution->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium text-slate-900">
                        {{ $contribution->member?->full_name ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $contribution->year }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        <span class="block text-xs text-slate-500">{{ $contribution->typeLabel() }}</span>{{ \App\Support\Months::short($contribution->month) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-700">
                        <x-money :value="$contribution->due_amount" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-700">
                        <x-money :value="$contribution->paid_amount" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold {{ $contribution->balance() > 0 ? 'text-rose-700' : 'text-emerald-700' }}">
                        <x-money :value="$contribution->balance()" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$contribution->status" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <div class="flex items-center justify-end gap-1">
                                @unless ($contribution->isPaid())
                                    <button type="button" wire:click="markAsPaid({{ $contribution->id }})"
                                        wire:confirm="Enregistrer le règlement de cette cotisation ?"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-600"
                                        title="Marquer comme payée">
                                        <x-icon name="sparkles" class="h-4 w-4" />
                                    </button>
                                @endunless
                                <x-row-actions edit="edit" :id="$contribution->id" remove="delete"
                                    :confirm="'Supprimer cette cotisation ?'" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->contributions->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier la cotisation' : 'Nouvelle cotisation'"
            subtitle="Enregistrez la cotisation attendue et son règlement.">
            <livewire:contributions.form :contribution="$this->editingContribution" />
        </x-modal-panel>
    @endif
</div>
