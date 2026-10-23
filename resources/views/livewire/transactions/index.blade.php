<div>
    <x-validation-summary />
    <x-page-header title="Recettes & dépenses" icon="banknotes"
        :subtitle="'Journal des opérations de la chorale — '.$this->stats['total'].' écriture(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle opération
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Recettes" :value="\App\Support\Money::compact($this->stats['recettes'])" icon="banknotes" color="green" :href="route('transactions.index', ['type'=>'recette','vue'=>'validees']).'#resultats'" />
        <x-stat-card label="Dépenses" :value="\App\Support\Money::compact($this->stats['depenses'])" icon="banknotes" color="red" :href="route('transactions.index', ['type'=>'depense','vue'=>'validees']).'#resultats'" />
        <x-stat-card label="Solde" :value="\App\Support\Money::compact($this->stats['solde'])" icon="chart" color="blue" :href="route('transactions.index', ['vue'=>'validees']).'#resultats'" />
        <x-stat-card label="Brouillons" :value="$this->stats['brouillons']" icon="folder" color="amber" :href="route('transactions.index', ['statut'=>'brouillon']).'#resultats'" />
    </div>

    @if ($overview !== '')
        <p class="my-3 text-sm text-slate-600">Sélection issue d’une carte de synthèse. <a class="underline" href="{{ url()->current() }}" wire:navigate>Tout afficher</a></p>
    @endif
    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-6">
            <div class="md:col-span-2">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Référence, libellé, poste…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="type" class="sr-only">Nature</label>
                <select id="type" wire:model.live="type"
                    class="form-select">
                    <option value="">Recettes & dépenses</option>
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
                <label for="category" class="sr-only">Poste</label>
                <select id="category" wire:model.live="category"
                    class="form-select">
                    <option value="">Tous les postes</option>
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <select id="month" wire:model.live="month"
                    class="form-select">
                    <option value="">Mois</option>
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \App\Support\Months::label($m) }}</option>
                    @endforeach
                </select>

                <select id="year" wire:model.live="year"
                    class="form-select">
                    <option value="">Année</option>
                    @foreach (range((int) now()->year, (int) now()->year - 4) as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->type !== '' || $this->status !== '' || $this->category !== '' || $this->month !== '' || $this->year !== '')
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
            ['label' => 'Référence'],
            ['label' => 'Date'],
            ['label' => 'Libellé'],
            ['label' => 'Nature'],
            ['label' => 'Poste'],
            ['label' => 'Compte'],
            ['label' => 'Montant', 'align' => 'text-right'],
            ['label' => 'Statut'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucune opération" empty-message="Aucune écriture ne correspond aux filtres sélectionnés."
            empty-icon="banknotes">
            @forelse ($this->transactions as $transaction)
                <tr wire:key="transaction-{{ $transaction->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-900">
                        {{ $transaction->reference }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $transaction->transaction_date->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm text-slate-800">{{ $transaction->description }}</p>
                        @if ($transaction->member || $transaction->activity)
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ $transaction->member?->full_name ?? $transaction->activity?->title }}
                            </p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$transaction->type" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $transaction->category ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $transaction->account?->code ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold {{ $transaction->type->value === 'recette' ? 'text-emerald-700' : 'text-rose-700' }}">
                        {{ $transaction->type->value === 'recette' ? '+' : '−' }} {{ \App\Support\Money::format($transaction->amount, false) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$transaction->status" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <div class="flex items-center justify-end gap-1">
                                @if ($transaction->status->value === 'brouillon')
                                    <button type="button" wire:click="validateTransaction({{ $transaction->id }})"
                                        wire:confirm="Valider cette écriture ?"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-600"
                                        title="Valider l'écriture">
                                        <x-icon name="sparkles" class="h-4 w-4" />
                                    </button>
                                @endif
                                @if (! $transaction->isLocked())
                                <x-row-actions edit="edit" :id="$transaction->id" remove="delete"
                                    :confirm="'Supprimer l\'écriture '.$transaction->reference.' ?'" />
                                @else
                                    <span class="text-xs text-slate-500">Écriture protégée</span>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->transactions->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'opération' : 'Nouvelle opération'"
            subtitle="La référence d'écriture (OP/AAAA/0000) est attribuée automatiquement.">
            <livewire:transactions.form :transaction="$this->editingTransaction" />
        </x-modal-panel>
    @endif
</div>
