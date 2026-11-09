<div>
    <x-validation-summary />
    <x-page-header title="Fiches de présence" icon="users"
        subtitle="Saisissez les faits, enregistrez un brouillon puis finalisez la fiche pour la transmettre à la comptabilité." />

    <div class="work-surface p-5">
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label for="attendance-type" class="form-label">Type d’événement</label>
                <select id="attendance-type" wire:model.live="eventType" class="form-select">
                    <option value="rehearsal">Répétition</option>
                    <option value="activity">Activité</option>
                </select>
            </div>
            <div>
                <label for="attendance-event" class="form-label">Événement</label>
                <select id="attendance-event" wire:model.live="eventId" class="form-select">
                    <option value="">Choisir…</option>
                    @foreach($this->events as $event)
                        <option value="{{ $event->id }}">
                            @if($eventType === 'rehearsal')
                                {{ $event->date->format('d/m/Y') }}{{ $event->venue ? ' — '.$event->venue : '' }}
                            @else
                                {{ $event->starts_at->format('d/m/Y') }} — {{ $event->title }}
                            @endif
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('eventId')" class="mt-1" />
            </div>
        </div>
    </div>

    @if($this->selectedEvent)
        @if($eventType === 'activity' && !$this->isFinalized())
            @can('manage-attendance')
                <section class="work-surface mt-5 p-5" aria-label="Ajouter des choristes à l’activité">
                    <h2 class="font-semibold">Choristes attendus pour cette activité</h2>
                    <p class="mt-1 text-sm text-slate-600">Sélectionnez les participants pour préparer la fiche. L’ajout ne les marque pas présents. Les frais de participation s’appliquent aux choristes actifs ; les intervenants extérieurs en sont exclus.</p>
                    <x-input id="attendance-member-search" name="memberSearch" label="Rechercher un choriste" wire:model.live.debounce.300ms="memberSearch" class="mt-3" />
                    <fieldset class="mt-3 max-h-60 space-y-2 overflow-y-auto rounded-lg border p-3">
                        <legend class="px-1 text-sm font-medium">Choristes actifs et intervenants disponibles</legend>
                        @forelse($this->availableMembers as $member)
                            <label wire:key="available-{{ $eventId }}-{{ $member->id }}" class="flex items-center gap-2"><input type="checkbox" wire:model="selectedMemberIds" value="{{ $member->id }}">{{ $member->full_name }}{{ $member->status === \App\Enums\MemberStatus::Externe ? ' · Intervenant extérieur' : '' }}</label>
                        @empty<p class="text-sm text-slate-600">Aucun autre participant disponible ne correspond à la recherche.</p>@endforelse
                    </fieldset>
                    <div class="mt-3 flex flex-wrap gap-3"><button type="button" wire:click="selectVisibleMembers" class="btn btn-secondary">Sélectionner la liste affichée</button><button type="button" wire:click="addSelectedMembers" wire:loading.attr="disabled" class="btn btn-primary">Ajouter à la fiche</button></div>
                </section>
            @endcan
        @endif
        <div class="work-surface mt-5">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="font-semibold text-slate-900">Feuille des choristes attendus</h2>
                    <p class="text-sm text-slate-600">
                        {{ count($rows) }} personne(s) ·
                        @if($this->isFinalized())
                            <span class="font-semibold text-emerald-700">Finalisée le {{ $this->selectedEvent->attendance_finalized_at->format('d/m/Y à H:i') }}</span>
                        @else
                            <span class="font-semibold text-amber-700">Brouillon</span>
                        @endif
                    </p>
                </div>
                @can('manage-attendance')
                    @if(!$this->isFinalized())
                        <button type="button" wire:click="markAllPresent" class="btn btn-soft">Marquer les non renseignés présents</button>
                    @endif
                @endcan
            </div>

            @if(count($rows) === 0)
                <div class="p-8 text-center text-sm text-slate-600">
                    {{ $eventType === 'activity' ? 'Aucun participant inscrit. Ajoutez les choristes attendus avec la sélection ci-dessus.' : 'Aucun choriste actif à afficher.' }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="table min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Choriste</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Présence</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Minutes de retard</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Motif accepté</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($rows as $index => $row)
                                <tr wire:key="attendance-row-{{ $eventType }}-{{ $eventId }}-{{ $row['member_id'] }}">
                                    <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $row['name'] }}</td>
                                    <td class="px-4 py-3">
                                        <select wire:model.live="rows.{{ $index }}.status" @disabled($this->isFinalized()) class="form-select min-w-36">
                                            <option value="">Non renseigné</option>
                                            @foreach($this->statusOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('rows.'.$index.'.status')" class="mt-1" />
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($row['status'] === 'late')
                                            <input type="number" min="1" max="1440" wire:model="rows.{{ $index }}.minutes_late" @disabled($this->isFinalized()) class="form-input w-32" aria-label="Minutes de retard de {{ $row['name'] }}">
                                            <x-input-error :messages="$errors->get('rows.'.$index.'.minutes_late')" class="mt-1" />
                                        @else <span class="text-slate-400">—</span> @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if($row['status'] === 'excuse')
                                            <input type="text" maxlength="1000" wire:model="rows.{{ $index }}.excuse_reason" @disabled($this->isFinalized()) class="form-input min-w-56" aria-label="Motif accepté pour {{ $row['name'] }}">
                                            <x-input-error :messages="$errors->get('rows.'.$index.'.excuse_reason')" class="mt-1" />
                                        @else <span class="text-slate-400">—</span> @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-200 px-5 py-4">
                <a href="{{ route('attendance.report', [$eventType === 'rehearsal' ? 'repetition' : 'activite', $eventId, 'document' => 'fiche']) }}" target="_blank" rel="noopener" class="btn btn-secondary">Imprimer la fiche de présence</a>
                @if($eventType === 'activity')<a href="{{ route('activities.detail-report', $eventId) }}" target="_blank" rel="noopener" class="btn btn-secondary">Rapport de l’activité</a>@endif
                @if(!$this->isFinalized())
                    <a href="{{ route('attendance.report', [$eventType === 'rehearsal' ? 'repetition' : 'activite', $eventId]) }}" target="_blank" rel="noopener" class="btn btn-secondary">Rapport provisoire (données enregistrées)</a>
                @endif
                @if($this->isFinalized())
                    <a href="{{ route('attendance.report', [$eventType === 'rehearsal' ? 'repetition' : 'activite', $eventId]) }}" target="_blank" rel="noopener" class="btn btn-primary"><x-icon name="archive" class="h-4 w-4" />Voir le rapport officiel</a>
                    @can('finalize-attendance')<x-confirm-action call="reopen" title="Corriger la fiche" message="Les incidents du rapport courant seront annulés." label="Corriger la fiche" />@endcan
                @elseif(count($rows) > 0)
                    @can('manage-attendance')<button type="button" wire:click="saveDraft" class="btn btn-secondary">Enregistrer le brouillon</button>@endcan
                    @can('finalize-attendance')<x-confirm-action call="finalize" title="Finaliser la fiche" message="Les absences et retards non excusés seront transmis à la comptabilité." label="Finaliser la fiche" trigger-class="btn btn-primary" />@endcan
                @endif
            </div>
        </div>
    @endif
</div>
