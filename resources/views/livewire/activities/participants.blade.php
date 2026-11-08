<div class="space-y-5">
    <section class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-4" aria-labelledby="batch-participants-title">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 id="batch-participants-title" class="font-semibold text-slate-900">Ajouter plusieurs participants</h3>
                <p class="mt-1 text-xs text-slate-600">Cochez les choristes à inscrire. Le rôle, les heures et la présence pourront être renseignés après l'inscription.</p>
            </div>
            <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-indigo-800">{{ count($selectedMemberIds) }} sélectionné(s)</span>
        </div>
        <div class="mt-4">
            <label for="participant-batch-search" class="form-label">Rechercher un choriste</label>
            <input id="participant-batch-search" type="search" wire:model.live.debounce.300ms="memberSearch"
                placeholder="Nom, prénom, e-mail…" class="form-input mt-1 w-full" />
        </div>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" wire:click="selectAllVisible" class="btn btn-secondary btn-sm">Tout sélectionner dans la liste</button>
            <button type="button" wire:click="clearSelection" class="btn btn-secondary btn-sm">Effacer la sélection</button>
        </div>
        <form wire:submit="addSelected" class="mt-3">
            <fieldset class="max-h-64 space-y-1 overflow-y-auto rounded-lg border border-slate-200 bg-white p-2" aria-describedby="participant-batch-help">
                <legend class="sr-only">Choristes à inscrire</legend>
                @forelse($this->availableMembers as $member)
                    <label wire:key="member-check-{{ $member->id }}" class="flex cursor-pointer items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-slate-50 focus-within:ring-2 focus-within:ring-indigo-500">
                        <input type="checkbox" value="{{ $member->id }}" wire:model.live="selectedMemberIds" class="h-4 w-4 rounded border-slate-300 text-indigo-700" />
                        <span class="font-medium text-slate-800">{{ $member->full_name }}{{ $member->status === \App\Enums\MemberStatus::Externe ? ' · Intervenant extérieur' : '' }}</span>
                    </label>
                @empty
                    <p class="p-3 text-sm text-slate-500">Aucun choriste disponible pour ce filtre.</p>
                @endforelse
            </fieldset>
            <p id="participant-batch-help" class="mt-2 text-xs text-slate-600">Les participants déjà inscrits ne seront pas dupliqués. Aucun statut de présence ne sera attribué automatiquement.</p>
            <x-input-error :messages="$errors->get('selectedMemberIds')" class="mt-2" />
            <x-input-error :messages="$errors->get('selectedMemberIds.*')" class="mt-2" />
            <button type="submit" class="btn btn-primary mt-4" @disabled(count($selectedMemberIds) === 0) wire:loading.attr="disabled" wire:target="addSelected">
                Inscrire les {{ count($selectedMemberIds) }} participant(s) sélectionné(s)
            </button>
        </form>
    </section>

    <details class="rounded-xl border border-slate-200 bg-white">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-slate-800">Ajout individuel avec détails (optionnel)</summary>
    <form wire:submit="add" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <p class="text-sm font-semibold text-slate-800">Ajouter un participant</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-input-label for="activities-participants-memberId" value="Choriste" />
                <select id="activities-participants-memberId" aria-describedby="activities-participants-memberId-error" aria-invalid="{{ $errors->has('memberId') ? 'true' : 'false' }}" wire:model="memberId"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->availableMembers as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}{{ $member->status === \App\Enums\MemberStatus::Externe ? ' · Intervenant extérieur' : '' }}</option>
                    @endforeach
                </select>
                <x-input-error id="activities-participants-memberId-error" :messages="$errors->get('memberId')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-participants-role" value="Rôle" />
                <select id="activities-participants-role" aria-describedby="activities-participants-role-error" aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}" wire:model="role"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->roleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="activities-participants-role-error" :messages="$errors->get('role')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-participants-status" value="Statut" />
                <select id="activities-participants-status" aria-describedby="activities-participants-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="activities-participants-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-participants-hours" value="Heures" />
                <x-text-input id="activities-participants-hours" type="number" step="0.5" min="0" name="hours" aria-describedby="activities-participants-hours-error" aria-invalid="{{ $errors->has('hours') ? 'true' : 'false' }}" wire:model="hours" class="mt-1 block w-full" />
                <x-input-error id="activities-participants-hours-error" :messages="$errors->get('hours')" class="mt-1" />
            </div>

            <div class="lg:col-span-2">
                <x-input-label for="activities-participants-notes" value="Notes" />
                <x-text-input id="activities-participants-notes" type="text" name="notes" aria-describedby="activities-participants-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" class="mt-1 block w-full" />
                <x-input-error id="activities-participants-notes-error" :messages="$errors->get('notes')" class="mt-1" />
            </div>

            <div class="flex items-end lg:col-span-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="add"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900/30">
                    Ajouter
                </button>
            </div>
        </div>
    </form>
    </details>

    <div>
        <div class="mb-2 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-800">
                Effectif participant
            </p>
            <p class="text-xs text-slate-500">
                Total : {{ $this->totalHours }} h
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Choriste</th>
                        <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Rôle</th>
                        <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Statut</th>
                        <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Heures</th>
                        <th scope="col" class="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse ($this->participants as $participant)
                        <tr wire:key="participant-{{ $participant->id }}" class="transition hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">
                                {{ $participant->full_name }}{{ $participant->status === \App\Enums\MemberStatus::Externe ? ' · Intervenant extérieur' : '' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                {{ $participant->pivot->role ? (\App\Enums\ActivityRole::tryFrom($participant->pivot->role)?->label() ?? $participant->pivot->role) : '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <select wire:change="updateStatus({{ $participant->id }}, $event.target.value)"
                                    class="rounded-lg border-slate-300 py-1 text-xs shadow-sm focus:border-slate-500 focus:ring-slate-500">
                                    @foreach ($this->statusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($participant->pivot->status === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-4 py-3">
                                <input type="number" step="0.5" min="0" value="{{ $participant->pivot->hours }}"
                                    wire:change="updateHours({{ $participant->id }}, $event.target.value)"
                                    class="w-20 rounded-lg border-slate-300 py-1 text-xs shadow-sm focus:border-slate-500 focus:ring-slate-500">
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <x-confirm-action :call="'remove('.$participant->id.')'" title="Retirer le participant" message="Le participant sera retiré de cette activité." label="Retirer" icon="trash" icon-only trigger-class="rounded-lg p-2 text-slate-500 transition hover:bg-red-50 hover:text-red-600" button-class="btn bg-red-600 text-white hover:bg-red-700" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">
                                Aucun participant inscrit pour cette activité.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
        <button type="button" wire:click="$dispatch('close-participants')"
            class="btn btn-primary">
            Fermer
        </button>
    </div>
</div>
