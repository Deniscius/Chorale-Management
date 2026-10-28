<div>
    <x-page-header title="Fiches de présence" icon="users"
        subtitle="Saisissez les faits, enregistrez un brouillon puis finalisez la fiche pour la transmettre à la comptabilité." />

    <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
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
        <div class="mt-5 rounded-xl border border-slate-200 bg-white shadow-sm">
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
                    {{ $eventType === 'activity' ? 'Ajoutez d’abord les participants à cette activité.' : 'Aucun choriste actif à afficher.' }}
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50"><tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Choriste</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Présence</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Minutes de retard</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-600">Motif accepté</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($rows as $index => $row)
                                <tr wire:key="attendance-row-{{ $row['member_id'] }}">
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
                @if($this->isFinalized())
                    <a href="{{ route('attendance.report', [$eventType === 'rehearsal' ? 'repetition' : 'activite', $eventId]) }}" target="_blank" rel="noopener" class="btn btn-primary"><x-icon name="archive" class="h-4 w-4" />Voir le rapport officiel</a>
                    @can('finalize-attendance')<button type="button" wire:click="reopen" wire:confirm="Rouvrir cette fiche ? Les incidents du rapport courant seront annulés." class="btn btn-secondary">Corriger la fiche</button>@endcan
                @elseif(count($rows) > 0)
                    @can('manage-attendance')<button type="button" wire:click="saveDraft" class="btn btn-secondary">Enregistrer le brouillon</button>@endcan
                    @can('finalize-attendance')<button type="button" wire:click="finalize" wire:confirm="Finaliser et transmettre les absences et retards non excusés à la comptabilité ?" class="btn btn-primary">Finaliser la fiche</button>@endcan
                @endif
            </div>
        </div>
    @endif
</div>
