<div>
    <x-page-header title="Budgets" icon="chart"
        :subtitle="'Lignes budgétaires par poste et par exercice — '.$this->stats['total'].' ligne(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle ligne
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Budget alloué" :value="\App\Support\Money::compact($this->stats['alloue'])" icon="banknotes" color="blue" />
        <x-stat-card label="Engagé" :value="\App\Support\Money::compact($this->stats['engage'])" icon="folder" color="amber" />
        <x-stat-card label="Réalisé" :value="\App\Support\Money::compact($this->stats['realise'])" icon="sparkles" color="green" />
        <x-stat-card label="Disponible" :value="\App\Support\Money::compact($this->stats['disponible'])" icon="chart" color="purple"
            :hint="$this->stats['taux'].' % consommés'" />
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
                        placeholder="Poste budgétaire…"
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
                <label for="category" class="sr-only">Poste</label>
                <select id="category" wire:model.live="category"
                    class="form-select">
                    <option value="">Tous les postes</option>
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->year !== '' || $this->category !== '')
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
            ['label' => 'Exercice'],
            ['label' => 'Poste'],
            ['label' => 'Alloué', 'align' => 'text-right'],
            ['label' => 'Engagé', 'align' => 'text-right'],
            ['label' => 'Réalisé', 'align' => 'text-right'],
            ['label' => 'Disponible', 'align' => 'text-right'],
            ['label' => 'Consommation'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun budget" empty-message="Aucune ligne budgétaire ne correspond aux filtres sélectionnés."
            empty-icon="chart">
            @forelse ($this->budgets as $budget)
                @php $rate = $budget->consumptionRate(); @endphp
                <tr wire:key="budget-{{ $budget->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-900">
                        {{ $budget->year }}
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm text-slate-800">{{ $budget->category }}</p>
                        @if ($budget->notes)
                            <p class="mt-0.5 text-xs text-slate-500">{{ \Illuminate\Support\Str::limit($budget->notes, 60) }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-700">
                        <x-money :value="$budget->allocated_amount" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-700">
                        <x-money :value="$budget->committed_amount" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-700">
                        <x-money :value="$budget->realized_amount" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-slate-900">
                        <x-money :value="$budget->available()" />
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100">
                                <div @class([
                                        'h-full rounded-full',
                                        'bg-emerald-500' => $rate < 70,
                                        'bg-amber-500' => $rate >= 70 && $rate < 100,
                                        'bg-rose-500' => $rate >= 100,
                                    ]) style="width: {{ min($rate, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-medium text-slate-600">{{ $rate }} %</span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <x-row-actions edit="edit" :id="$budget->id" remove="delete"
                                :confirm="'Supprimer la ligne budgétaire « '.$budget->category.' '.$budget->year.' » ?'" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->budgets->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier la ligne budgétaire' : 'Nouvelle ligne budgétaire'"
            subtitle="Répartissez le budget de la chorale par poste et par exercice.">
            <livewire:budgets.form :budget="$this->editingBudget" />
        </x-modal-panel>
    @endif
</div>
