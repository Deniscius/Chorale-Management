<div>
    @php $archiveRoute = $correspondenceId ? 'correspondences.documents' : 'documents.index'; $archiveParameters = $correspondenceId ? ['correspondence' => $correspondenceId] : []; @endphp
    <x-page-header title="Archives documentaires" icon="archive"
        subtitle="Programmes de messe, partitions et pièces de courrier conservés pour les consultations ultérieures.">
        <x-slot name="actions">
            @if ($this->canConfigureTeam)
                <button type="button" wire:click="configureTeam" class="btn btn-secondary">Responsable et associé</button>
            @endif
            @if ($this->canUploadCurrent)
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="upload" class="h-4 w-4" />
                    Importer un document
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Documents accessibles" :value="$this->stats['total']" icon="archive" color="slate" :href="route($archiveRoute, $archiveParameters + ['commission'=>$commission]).'#resultats'" />
        <x-stat-card label="Privés" :value="$this->stats['prives']" icon="folder" color="blue" :href="route($archiveRoute, $archiveParameters + ['commission'=>$commission,'visibilite'=>'private']).'#resultats'" />
        <x-stat-card label="Partagés" :value="$this->stats['partages']" icon="users" color="green" :href="route($archiveRoute, $archiveParameters + ['commission'=>$commission,'visibilite'=>'published']).'#resultats'" />
        <x-stat-card label="Volume accessible" :value="\App\Support\Files::size($this->stats['octets'])" icon="folder" color="violet" :href="route($archiveRoute, $archiveParameters + ['commission'=>$commission]).'#resultats'" />
    </div>
    @if (\App\Enums\Commission::from($commission)->hasPrivateArchive())
        <p class="mt-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-900">Privé : responsable et associé désignés uniquement. Partagé : tous les utilisateurs connectés. Les compteurs affichent uniquement les documents qui vous sont accessibles.</p>
    @endif

    {{-- Onglets par commission --}}
    <div class="mt-6 flex flex-wrap gap-2" role="tablist" aria-label="Commissions">
        @foreach ($this->allowedCommissions as $option)
            @php $active = $this->commission === $option->value; @endphp
            <button type="button" wire:click="$set('commission', '{{ $option->value }}')" role="tab"
                aria-selected="{{ $active ? 'true' : 'false' }}"
                @class([
                    'inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-sm font-medium transition',
                    'border-slate-900 bg-slate-900 text-white' => $active,
                    'border-slate-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-900' => ! $active,
                ])>
                {{ $option->label() }}
                <span @class([
                    'rounded-full px-1.5 py-0.5 text-[11px] font-semibold',
                    'bg-white/20 text-white' => $active,
                    'bg-slate-100 text-slate-600' => ! $active,
                ])>{{ $this->countsByCommission[$option->value] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <x-filter-bar class="mt-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="md:col-span-2">
                <label for="search" class="sr-only">Rechercher</label>
                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>
                    <input id="search" type="search" wire:model.live.debounce.300ms="search"
                        placeholder="Titre, description ou nom de fichier…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="category" class="sr-only">Catégorie</label>
                <select id="category" wire:model.live="category"
                    class="form-select">
                    <option value="">Toutes les catégories</option>
                    @foreach ($this->categories as $cat)
                        <option value="{{ $cat }}">{{ $cat }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 grid gap-3 sm:grid-cols-2">
            <x-select name="visibility" label="Visibilité" wire:model.live="visibility">
                <option value="">Toutes les visibilités</option><option value="private">Privés</option><option value="published">Partagés</option>
            </x-select>
            <x-input name="year" label="Année du document" type="number" min="1000" max="9999" wire:model.live.debounce.300ms="year" />
        </div>

        @if ($this->search !== '' || $this->category !== '' || $this->visibility !== '' || $this->year !== '')
            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-xs text-slate-500">Filtres actifs</span>
                <button type="button" wire:click="resetFilters"
                    class="btn btn-soft btn-sm">
                    Tout réinitialiser
                </button>
            </div>
        @endif
    </x-filter-bar>

    <div id="resultats" class="mt-4 scroll-mt-20">
        <x-data-table :headers="[
            ['label' => 'Document'],
            ['label' => 'Catégorie / visibilité'],
            ['label' => 'Taille', 'align' => 'text-right'],
            ['label' => 'Date du document'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun document"
            empty-message="Aucun document accessible ne correspond à cette sélection."
            empty-icon="archive">
            @forelse ($this->documents as $document)
                <tr wire:key="document-{{ $document->id }}" class="transition hover:bg-slate-50">
                    <td class="px-4 py-3">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                <x-icon name="archive" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $document->title }}</p>
                                <p class="truncate text-xs text-slate-500">{{ $document->original_name }}</p>
                                @if ($document->description)
                                    <p class="mt-0.5 line-clamp-1 text-xs text-slate-400">{{ $document->description }}</p>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        @if ($document->commission->hasPrivateArchive())
                            <x-badge>{{ $document->visibility === 'published' ? 'Partagé' : 'Privé' }}</x-badge>
                        @endif
                        @if ($document->category)
                            <x-badge>{{ $document->category }}</x-badge>
                        @else
                            <span class="text-sm text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right text-sm text-slate-600">
                        {{ $document->humanSize() }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ ($document->document_date ?? $document->created_at)->format('d/m/Y') }}
                        @if ($document->uploader)
                            <p class="text-xs text-slate-400">{{ $document->uploader->name }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-0.5">
                            @if ($document->fileExists())
                                <a href="{{ route('documents.download', $document) }}" target="_blank"
                                    rel="noopener" title="Consulter le document" aria-label="Consulter le document"
                                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                    <x-icon name="download" class="h-4 w-4" />
                                </a>
                            @else
                                <span class="px-2 text-xs text-rose-500" title="Fichier introuvable">absent</span>
                            @endif

                            @if ($document->commission->hasPrivateArchive() && $this->canUpload($document->commission))
                                <button type="button" class="btn btn-soft btn-sm" wire:click="share({{ $document->id }}, {{ $document->visibility === 'published' ? 'false' : 'true' }})"
                                    wire:confirm="{{ $document->visibility === 'published' ? 'Retirer le partage de ce document ?' : 'Partager ce fichier avec tous les utilisateurs connectés ?' }}">
                                    {{ $document->visibility === 'published' ? 'Rendre privé' : 'Partager' }}
                                </button>
                            @endif
                            @if ($this->canUpload($document->commission))
                                <x-row-actions edit="edit" :id="$document->id" remove="delete"
                                    :confirm="'Retirer ce document de l\'archive ?'" />
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->documents->links() }}
    </div>

    @if ($showTeam && $this->canConfigureTeam)
        <x-modal-panel title="Accès aux archives" size="lg">
            <livewire:documents.team-settings :commission="$this->commission" :key="'team-'.$this->commission" />
        </x-modal-panel>
    @endif
    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier le document' : 'Importer un document'"
            subtitle="Le document sera conservé dans l'archive de la commission sélectionnée.">
            <livewire:documents.form :document="$this->editingDocument" :commission="$this->commission" :correspondence-id="$this->correspondenceId" />
        </x-modal-panel>
    @endif
</div>
