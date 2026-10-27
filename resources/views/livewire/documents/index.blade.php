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
        <x-stat-card label="Responsables habilités" :value="$this->stats['prives']" icon="folder" color="blue" :href="route($archiveRoute, $archiveParameters + ['commission'=>$commission,'visibilite'=>'private']).'#resultats'" />
        <x-stat-card label="Toute la chorale" :value="$this->stats['partages']" icon="users" color="green" :href="route($archiveRoute, $archiveParameters + ['commission'=>$commission,'visibilite'=>'published']).'#resultats'" />
        <x-stat-card label="Volume accessible" :value="\App\Support\Files::size($this->stats['octets'])" icon="folder" color="violet" :href="route($archiveRoute, $archiveParameters + ['commission'=>$commission]).'#resultats'" />
    </div>
    <p class="mt-4 rounded-lg bg-blue-50 p-3 text-sm text-blue-950">Chaque document peut être réservé aux responsables habilités, accessible selon les droits de la commission ou partagé avec toute la chorale connectée. Aucun fichier n’est public sur Internet. Les compteurs ne montrent que les documents que vous pouvez consulter.</p>

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
                <option value="">Toutes les visibilités</option>@foreach(\App\Enums\DocumentVisibility::options() as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
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
                        <x-badge>{{ \App\Enums\DocumentVisibility::tryFrom($document->visibility)?->label() ?? 'Visibilité inconnue' }}</x-badge>
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
                                @if($document->isPreviewable())
                                    <button type="button" wire:click="preview({{ $document->id }})"
                                        title="Aperçu sans téléchargement" aria-label="Aperçu de {{ $document->title }}"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-indigo-50 hover:text-indigo-700">
                                        <x-icon name="eye" class="h-4 w-4" />
                                    </button>
                                @endif
                                <a href="{{ route('documents.download', $document) }}"
                                    title="Télécharger le document" aria-label="Télécharger {{ $document->title }}"
                                    class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700">
                                    <x-icon name="download" class="h-4 w-4" />
                                </a>
                            @else
                                <span class="px-2 text-xs text-rose-500" title="Fichier introuvable">absent</span>
                            @endif

                            @if ($this->canUpload($document->commission))
                                <button type="button" class="btn btn-soft btn-sm" wire:click="changeVisibility({{ $document->id }})">Visibilité</button>
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
        <x-modal-panel close-event="close-team" title="Accès aux archives" size="lg">
            <livewire:documents.team-settings :commission="$this->commission" :key="'team-'.$this->commission" />
        </x-modal-panel>
    @endif
    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier le document' : 'Importer un document'"
            subtitle="Le document sera conservé dans l'archive de la commission sélectionnée.">
            <livewire:documents.form :document="$this->editingDocument" :commission="$this->commission" :correspondence-id="$this->correspondenceId" />
        </x-modal-panel>
    @endif
    @if($visibilityDocumentId)
        <x-modal-panel title="Modifier la visibilité" subtitle="Choisissez qui peut consulter ce document dans la chorale." close-event="close-visibility">
            <livewire:documents.visibility-form :document="$this->visibilityDocument" :key="'visibility-'.$visibilityDocumentId" />
        </x-modal-panel>
    @endif
    @if($previewDocumentId && $this->previewDocument)
        <x-modal-panel :title="$this->previewDocument->title"
            subtitle="Aperçu sécurisé dans la plateforme — le téléchargement reste une action séparée."
            close-event="close-preview" size="3xl">
            @if($this->previewDocument->previewType() === 'image')
                <div class="flex min-h-64 items-center justify-center rounded-xl bg-slate-100 p-3 sm:min-h-[60vh]">
                    <img src="{{ route('documents.preview', $this->previewDocument) }}"
                        alt="Aperçu de {{ $this->previewDocument->title }}"
                        class="max-h-[70vh] max-w-full rounded-lg object-contain">
                </div>
            @else
                <iframe src="{{ route('documents.preview', $this->previewDocument) }}"
                    title="Aperçu de {{ $this->previewDocument->title }}"
                    class="h-[65vh] min-h-80 w-full rounded-xl border border-slate-200 bg-white"></iframe>
            @endif
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <p class="text-sm text-slate-600">{{ $this->previewDocument->original_name }} · {{ $this->previewDocument->humanSize() }}</p>
                <a href="{{ route('documents.download', $this->previewDocument) }}" class="btn btn-secondary">
                    <x-icon name="download" class="h-4 w-4" /> Télécharger une copie
                </a>
            </div>
        </x-modal-panel>
    @endif
</div>
