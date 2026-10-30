<div>
    <x-page-header title="Choristes" icon="users"
        :subtitle="'Effectif, voix et implications de la chorale — '.$this->stats['total'].' fiche(s) enregistrée(s).'">
        <x-slot name="actions">
            @can('manage-module', \App\Enums\UserRole::MODULE_MEMBERS)
                <a href="{{ route('members.report') }}" target="_blank" rel="noopener" class="btn btn-secondary">
                    <x-icon name="archive" class="h-4 w-4" /> Rapport général
                </a>
            @endcan
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau choriste
                </button>
            @endif
        </x-slot>
    </x-page-header>

    {{-- Indicateurs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Effectif total" :value="$this->stats['total']" icon="users" color="slate" :href="route('members.index', []).'#resultats'" />
        <x-stat-card label="Choristes actifs" :value="$this->stats['actif']" icon="users" color="green" :href="route('members.index', ['statut'=>'actif']).'#resultats'" />
        <x-stat-card label="En attente d'adhésion" :value="$this->stats['invite']" icon="inbox" color="amber" :href="route('members.index', ['statut'=>'invite']).'#resultats'" />
        <x-stat-card label="Répartition" :value="count($this->stats['par_voix'])" icon="music" color="purple"
            hint="voix différentes représentées" :href="route('members.index', []).'#resultats'" />
    </div>

    {{-- Filtres --}}
    <div class="mt-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div class="grid gap-3 md:grid-cols-4">
            <div class="md:col-span-2">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Rechercher un nom, e-mail ou téléphone…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="voice" class="sr-only">Voix</label>
                <select id="voice" wire:model.live="voice"
                    class="form-select">
                    <option value="">Toutes les voix</option>
                    @foreach ($this->voiceOptions as $value => $label)
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
        </div>

        @if ($this->search !== '' || $this->voice !== '' || $this->status !== '' || $this->choirId)
            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-xs text-slate-500">Filtres actifs</span>
                <button type="button" wire:click="resetFilters"
                    class="btn btn-soft btn-sm">
                    Tout réinitialiser
                </button>
            </div>
        @endif
    </div>

    {{-- Liste --}}
    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table id="resultats" class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Choriste</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Voix / pupitre</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Rôle</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Contact</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Statut</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">Adhésion</th>
                        @if ($this->canManage())
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-500">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($this->members as $member)
                        <tr wire:key="member-{{ $member->id }}" class="transition hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                        {{ $member->initials }}
                                    </span>
                                    <div>
                                        <a href="{{ route('members.show', $member) }}" wire:navigate
                                            class="text-sm font-semibold text-slate-900 underline decoration-slate-300 underline-offset-2 hover:text-blue-700 hover:decoration-blue-400">
                                            {{ $member->full_name }}
                                        </a>
                                        @if ($member->choir)
                                            <p class="text-xs text-slate-500">{{ $member->choir->name }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($member->voice)
                                    <x-enum-badge :enum="$member->voice" />
                                    @if ($member->part)
                                        <span class="ml-1 text-xs font-semibold text-slate-500">{{ $member->part }}</span>
                                    @endif
                                @else
                                    <span class="text-sm text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($member->accessRole())
                                    <x-enum-badge :enum="$member->accessRole()" />
                                @elseif ($member->invitation_exists)
                                    <span class="text-xs font-medium text-amber-700">Activation en attente</span>
                                @else
                                    <span class="text-sm text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                @if ($member->email)
                                    <a href="mailto:{{ $member->email }}" class="text-slate-700 underline decoration-slate-300 hover:decoration-slate-500">{{ $member->email }}</a>
                                @endif
                                @if ($member->phone)
                                    <p class="text-xs text-slate-500">{{ $member->phone }}</p>
                                @endif
                                @if (! $member->email && ! $member->phone)
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-enum-badge :enum="$member->status" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                                {{ $member->joined_on?->format('d/m/Y') ?? '—' }}
                            </td>
                            @if ($this->canManage())
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        @can('manage-users')
                                            <button type="button" wire:click="manageAccess({{ $member->id }})"
                                                class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                                title="{{ $member->hasAccount() ? "Modifier le rôle" : "Attribuer un rôle" }}">
                                                <x-icon name="lock" class="h-4 w-4" />
                                            </button>
                                        @endcan
                                        <button type="button" wire:click="edit({{ $member->id }})"
                                            class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                            title="Modifier">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" />
                                            </svg>
                                        </button>
                                        <x-confirm-action :call="'delete('.$member->id.')'" title="Supprimer le choriste"
                                            :message="'Retirer '.$member->full_name.' de la liste des choristes ?'" label="Supprimer" icon="trash" icon-only
                                            trigger-class="rounded-lg p-2 text-slate-500 transition hover:bg-red-50 hover:text-red-600" button-class="btn bg-red-600 text-white hover:bg-red-700" />
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $this->canManage() ? 7 : 6 }}" class="px-4 py-10 text-center">
                                <x-empty-state icon="users" title="Aucun choriste trouvé"
                                    message="Modifiez vos filtres ou enregistrez un premier choriste." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $this->members->links() }}
    </div>

    @if ($showForm)
        <div wire:key="member-form">
            <x-modal-panel :title="$editingId ? 'Modifier le choriste' : 'Nouveau choriste'"
                subtitle="Renseignez la fiche du choriste.">
                <livewire:members.form :member="$this->editingMember" />
            </x-modal-panel>
        </div>
    @endif

    @if ($showAccess && $this->accessMember)
        <div wire:key="member-access">
            <x-modal-panel close-event="close-access" title="Rôle d'accès du choriste"
                subtitle="Préparez son accès personnel et choisissez ses modules.">
                <livewire:members.access :member="$this->accessMember" />
            </x-modal-panel>
        </div>
    @endif
</div>
