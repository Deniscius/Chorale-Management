<div>
    <x-page-header title="Ensembles instrumentaux" icon="sparkles"
        :subtitle="'Groupes de musiciens de la chorale — '.$this->stats['total'].' ensemble(s) constitué(s).'">
        <x-slot name="actions">
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouvel ensemble
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat-card label="Ensembles" :value="$this->stats['total']" icon="sparkles" color="slate" />
        <x-stat-card label="Effectif cumulé" :value="$this->stats['effectif']" icon="users" color="purple" />
        <x-stat-card label="Effectif moyen" :value="$this->stats['moyenne']" icon="chart" color="green" />
    </div>

    <x-filter-bar class="mt-6">
        <div>
            <label for="search" class="sr-only">Rechercher</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input id="search" type="search" wire:model.live.debounce.300ms="search"
                    placeholder="Rechercher un ensemble…"
                    class="form-input form-input-icon pr-3">
            </div>
        </div>
    </x-filter-bar>

    <div class="mt-4">
        <x-data-table :headers="[
            ['label' => 'Ensemble'],
            ['label' => 'Chef d\'ensemble'],
            ['label' => 'Effectif'],
            ['label' => 'Description'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun ensemble" empty-message="Créez les ensembles instrumentaux de la commission."
            empty-icon="sparkles">
            @forelse ($this->ensembles as $ensemble)
                <tr wire:key="ensemble-{{ $ensemble->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                        {{ $ensemble->name }}
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($ensemble->conductor)
                            {{ $ensemble->conductor->full_name }}
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $ensemble->member_count }} membre(s)
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500">
                        {{ \Illuminate\Support\Str::limit($ensemble->description, 70) ?: '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ($this->canManage())
                            <x-row-actions edit="edit" :id="$ensemble->id" remove="delete"
                                :confirm="'Supprimer l\'ensemble « '.$ensemble->name.' » ?'" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->ensembles->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier l\'ensemble' : 'Nouvel ensemble'"
            subtitle="Constituez un groupe de musiciens et désignez son chef d'ensemble.">
            <livewire:instruments.ensemble-form :ensemble="$this->editingEnsemble" />
        </x-modal-panel>
    @endif
</div>
