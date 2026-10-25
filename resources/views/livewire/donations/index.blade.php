<div>
    <x-validation-summary />
    <x-page-header :title="app(\App\Services\FinancialCatalog::class)->section('donation')" icon="sparkles"
        :subtitle="'Contributions reçues par la chorale — '.$this->stats['nombre'].' entrée(s).' ">
        <x-slot name="actions">
            @can('manage-settings')<a href="{{ route('settings.financial-types') }}" class="btn btn-secondary">Configurer les types</a>@endcan
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvelle entrée
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total reçu" :value="\App\Support\Money::compact($this->stats['total'])" icon="banknotes" color="green" :href="route('donations.index', []).'#resultats'" />
        <x-stat-card label="Nombre d’entrées" :value="$this->stats['nombre']" icon="sparkles" color="slate" :href="route('donations.index', []).'#resultats'" />
        <x-stat-card label="Montant moyen" :value="\App\Support\Money::compact($this->stats['moyenne'])" icon="chart" color="blue" :href="route('donations.index', []).'#resultats'" />
        <x-stat-card label="Montant le plus élevé" :value="\App\Support\Money::compact($this->stats['max'])" icon="folder" color="violet" :href="route('donations.index', ['vue'=>'montant']).'#resultats'" />
        <x-stat-card label="Nature principale"
            :value="$this->stats['type_dominant'] ? app(\App\Services\FinancialCatalog::class)->label($this->stats['type_dominant']) : '—'"
            :hint="$this->stats['type_dominant'] ? \App\Support\Money::compact($this->stats['type_dominant_montant']) : null"
            icon="sparkles" color="amber" :href="route('donations.index', ['nature'=>$this->stats['type_dominant']]).'#resultats'" />
    </div>

    @if ($overview !== '')
        <p class="my-3 text-sm text-slate-600">Sélection issue d’une carte de synthèse. <a class="underline" href="{{ url()->current() }}" wire:navigate>Tout afficher</a></p>
    @endif
    <x-filter-bar class="mt-6">
        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <div>
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Donateur, motif…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="donorType" class="sr-only">Type de donateur</label>
                <select id="donorType" wire:model.live="donorType"
                    class="form-select">
                    <option value="">Tous les donateurs</option>
                    @foreach ($this->donorTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="donationType" class="sr-only">Nature du don</label>
                <select id="donationType" wire:model.live="donationType"
                    class="form-select">
                    <option value="">Toutes les natures</option>
                    @foreach ($this->donationTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="year" class="sr-only">Année</label>
                <select id="year" wire:model.live="year"
                    class="form-select">
                    <option value="">Toutes les années</option>
                    @foreach ($this->years as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->donorType !== '' || $this->donationType !== '' || $this->year !== '')
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
            ['label' => 'Donateur'],
            ['label' => 'Nature'],
            ['label' => 'Type de donateur'],
            ['label' => 'Motif'],
            ['label' => 'Montant', 'align' => 'text-right'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun don" empty-message="Aucun don ne correspond aux filtres sélectionnés."
            empty-icon="sparkles">
            @forelse ($this->donations as $donation)
                <tr wire:key="donation-{{ $donation->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $donation->donated_on->format('d/m/Y') }}
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-900">
                        {{ $donation->donor_name ?? 'Don anonyme' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        @if ($donation->donation_type)
                            <span class="text-sm font-medium text-slate-700">{{ $donation->typeLabel() }}</span>
                        @else
                            <span class="text-sm text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$donation->donor_type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        {{ $donation->purpose ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold text-emerald-700">
                        {{ \App\Support\Money::format($donation->amount) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <x-row-actions edit="edit" :id="$donation->id" remove="delete"
                                :confirm="'Supprimer ce don ?'" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->donations->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier la contribution' : 'Nouvelle contribution'"
            subtitle="Enregistrez une contribution reçue par la chorale et son affectation.">
            <livewire:donations.form :donation="$this->editingDonation" />
        </x-modal-panel>
    @endif
</div>
