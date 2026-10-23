<div>
    <x-page-header title="Rappels de répétition" icon="bell"
        :subtitle="'Journal des messages envoyés aux choristes — '.$this->stats['total'].' envoi(s) enregistré(s).'" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Rappels journalisés" :value="$this->stats['total']" icon="bell" color="slate" />
        <x-stat-card label="Envoyés" :value="$this->stats['envoyes']" icon="check" color="green" />
        <x-stat-card label="Échecs" :value="$this->stats['echecs']" icon="alert" color="red" />
        <x-stat-card label="Par SMS" :value="$this->stats['sms']" icon="chat" color="blue" />
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
                        placeholder="Destinataire, objet…"
                        class="form-input form-input-icon pr-3">
                </div>
            </div>

            <div>
                <label for="channel" class="sr-only">Canal</label>
                <select id="channel" wire:model.live="channel" class="form-select">
                    <option value="">Tous les canaux</option>
                    @foreach ($this->channelOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="status" class="sr-only">Statut</label>
                <select id="status" wire:model.live="status" class="form-select">
                    <option value="">Tous les statuts</option>
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($this->search !== '' || $this->channel !== '' || $this->status !== '')
            <div class="mt-3 flex items-center gap-2 border-t border-slate-100 pt-3">
                <span class="text-xs text-slate-500">Filtres actifs</span>
                <button type="button" wire:click="resetFilters" class="btn btn-soft btn-sm">
                    Tout réinitialiser
                </button>
            </div>
        @endif
    </x-filter-bar>

    <div class="mt-4">
        <x-data-table :headers="[
            ['label' => 'Date d’envoi'],
            ['label' => 'Destinataire'],
            ['label' => 'Répétition'],
            ['label' => 'Canal'],
            ['label' => 'Statut'],
        ]" empty-title="Aucun rappel" empty-message="Aucun rappel ne correspond aux filtres sélectionnés."
            empty-icon="bell">
            @forelse ($this->reminders as $reminder)
                <tr wire:key="reminder-{{ $reminder->id }}" class="transition hover:bg-slate-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm text-slate-600">
                        {{ $reminder->sent_at?->translatedFormat('d M Y · H:i') ?? '—' }}
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-slate-800">
                            {{ $reminder->member?->full_name ?? '—' }}
                        </p>
                        <p class="text-xs text-slate-500">{{ $reminder->recipient }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        @if ($reminder->rehearsal)
                            {{ $reminder->rehearsal->date->translatedFormat('d M Y') }}
                            <p class="text-xs text-slate-500">{{ $reminder->rehearsal->venue ?: '—' }}</p>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$reminder->channel" />
                    </td>
                    <td class="whitespace-nowrap px-4 py-3">
                        <x-enum-badge :enum="$reminder->status" />
                        @if ($reminder->status === \App\Enums\ReminderStatus::Incertain && $this->canManage())
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button class="btn btn-secondary" wire:click="resolve({{ $reminder->id }}, 'sent')" wire:confirm="Confirmez-vous avoir vérifié que le fournisseur a bien envoyé ce rappel ?">Déjà envoyé</button>
                                <button class="btn btn-secondary" wire:click="resolve({{ $reminder->id }}, 'retry')" wire:confirm="Après vérification du fournisseur, confirmez la relance. Elle peut créer un doublon si le premier envoi a déjà abouti.">Relancer</button>
                                <button class="btn btn-secondary" wire:click="resolve({{ $reminder->id }}, 'ignore')">Ignorer</button>
                            </div>
                        @endif
                        @if ($reminder->error)
                            <p class="mt-1 max-w-xs truncate text-xs text-red-600" title="{{ $reminder->error }}">
                                {{ $reminder->error }}
                            </p>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    </div>

    <div class="mt-4">
        {{ $this->reminders->links() }}
    </div>
</div>
