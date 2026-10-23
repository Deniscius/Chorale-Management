<div>
    <x-page-header title="Registre des courriers" icon="mail"
        :subtitle="'Courriers entrants et sortants — '.$this->stats['total'].' courrier(s) enregistré(s).'">
        <x-slot name="actions">
            <a href="{{ route('documents.index',['commission'=>'commission_secretariat']) }}" wire:navigate class="btn btn-secondary">Archives des courriers</a>
            @if ($this->canManage())
                <button type="button" wire:click="create"
                    class="btn btn-primary">
                    <x-icon name="plus" class="h-4 w-4" />
                    Nouveau courrier
                </button>
            @endif
        </x-slot>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Courriers" :value="$this->stats['total']" icon="mail" color="slate" :href="route('correspondences.index', []).'#resultats'" />
        <x-stat-card label="Entrants" :value="$this->stats['entrants']" icon="inbox" color="sky"
            :hint="$this->stats['sortants'].' sortant(s)'" :href="route('correspondences.index', ['type'=>'entrant']).'#resultats'" />
        <x-stat-card label="À traiter" :value="$this->stats['a_traiter']" icon="chart" color="amber" :href="route('correspondences.index', ['vue'=>'ouverts']).'#resultats'" />
        <x-stat-card label="Confidentiels" :value="$this->stats['confidentiels']" icon="folder" color="violet" :href="route('correspondences.index', ['vue'=>'confidentiels']).'#resultats'" />
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
                        placeholder="Référence, objet, expéditeur…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="type" class="sr-only">Type</label>
                <select id="type" wire:model.live="type"
                    class="form-select">
                    <option value="">Entrants & sortants</option>
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
                <label for="channel" class="sr-only">Canal</label>
                <select id="channel" wire:model.live="channel"
                    class="form-select">
                    <option value="">Tous les canaux</option>
                    @foreach ($this->channelOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <select id="serviceId" wire:model.live="serviceId"
                    class="form-select">
                    <option value="">Tous les services</option>
                    @foreach ($this->services as $service)
                        <option value="{{ $service->id }}">{{ $service->name }}</option>
                    @endforeach
                </select>

                <select id="year" wire:model.live="year"
                    class="form-select">
                    <option value="">Année</option>
                    @foreach ($this->years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
            @if ($this->search !== '' || $this->type !== '' || $this->status !== '' || $this->channel !== '' || $this->serviceId || $this->year !== '')
                <span class="text-xs text-slate-500">Filtres actifs</span>
                <button type="button" wire:click="resetFilters"
                    class="btn btn-soft btn-sm">
                    Tout réinitialiser
                </button>
            @else
                <span class="text-xs text-slate-500">Services destinataires</span>
            @endif

            @if ($this->canManage())
                <button type="button" wire:click="$toggle('showServiceForm')"
                    class="btn btn-soft btn-sm">
                    {{ $showServiceForm ? 'Annuler' : 'Ajouter un service' }}
                </button>
            @endif
        </div>

        @if ($showServiceForm)
            <form wire:submit="addService" class="mt-3 grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 sm:grid-cols-3">
                <div class="sm:col-span-2">
                    <x-input-label for="serviceName" value="Nom du service" />
                    <x-text-input id="serviceName" type="text" wire:model="serviceName" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('serviceName')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="serviceDescription" value="Description" />
                    <x-text-input id="serviceDescription" type="text" wire:model="serviceDescription" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('serviceDescription')" class="mt-1" />
                </div>
                <div class="sm:col-span-3 flex justify-end">
                    <button type="submit"
                        class="btn btn-primary">
                        Enregistrer le service
                    </button>
                </div>
            </form>
        @endif
    </x-filter-bar>

    <div class="mt-4">
        <x-data-table id="resultats" :headers="[
            ['label' => 'Référence'],
            ['label' => 'Date'],
            ['label' => 'Type'],
            ['label' => 'Correspondant'],
            ['label' => 'Objet'],
            ['label' => 'Service'],
            ['label' => 'Statut'],
            ['label' => 'Actions', 'align' => 'text-right'],
        ]" empty-title="Aucun courrier" empty-message="Aucun courrier ne correspond aux filtres sélectionnés."
            empty-icon="mail">
            @forelse ($this->correspondences as $correspondence)
                <tr wire:key="correspondence-{{ $correspondence->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $correspondence->reference }}</p>
                        @if ($correspondence->confidentiality->value === 'confidentiel')
                            <p class="mt-0.5 text-xs font-medium text-violet-600">Confidentiel</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $correspondence->correspondence_date->format('d/m/Y') }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$correspondence->type" />
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($correspondence->type->value === 'entrant')
                            <p>{{ $correspondence->sender_name }}</p>
                            @if ($correspondence->sender_organization)
                                <p class="text-xs text-slate-500">{{ $correspondence->sender_organization }}</p>
                            @endif
                        @else
                            <p>{{ $correspondence->recipient_name ?? '—' }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        {{ \Illuminate\Support\Str::limit($correspondence->subject, 55) }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $correspondence->service?->name ?? '—' }}
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$correspondence->status" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        <a href="{{ route('correspondences.documents',$correspondence) }}" wire:navigate class="btn btn-soft btn-sm" aria-label="Pièces jointes du courrier {{ $correspondence->reference }}">Pièces jointes</a>
                        @if ($this->canManage())
                            <div class="flex items-center justify-end gap-1">
                                @unless (in_array($correspondence->status->value, ['traite', 'clos', 'archive'], true))
                                    <button type="button" wire:click="markAsProcessed({{ $correspondence->id }})"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-emerald-50 hover:text-emerald-600"
                                        title="Marquer comme traité">
                                        <x-icon name="sparkles" class="h-4 w-4" />
                                    </button>
                                    <button type="button" wire:click="close({{ $correspondence->id }})"
                                        class="rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                                        title="Clôturer le courrier">
                                        <x-icon name="folder" class="h-4 w-4" />
                                    </button>
                                @endunless
                                <x-row-actions edit="edit" :id="$correspondence->id" remove="delete"
                                    :confirm="'Supprimer le courrier '.$correspondence->reference.' ?'" />
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->correspondences->links() }}
    </div>

    @if ($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier le courrier' : 'Nouveau courrier'"
            subtitle="La référence (chrono) est attribuée automatiquement à l'enregistrement.">
            <livewire:correspondences.form :correspondence="$this->editingCorrespondence" />
        </x-modal-panel>
    @endif
</div>
