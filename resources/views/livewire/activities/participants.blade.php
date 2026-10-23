<div class="space-y-5">
    <form wire:submit="add" class="rounded-lg border border-slate-200 bg-slate-50 p-4">
        <p class="text-sm font-semibold text-slate-800">Ajouter un participant</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-input-label for="memberId" value="Choriste" />
                <select id="memberId" wire:model="memberId"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->availableMembers as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('memberId')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="role" value="Rôle" />
                <select id="role" wire:model="role"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->roleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="status" value="Statut" />
                <select id="status" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="hours" value="Heures" />
                <x-text-input id="hours" type="number" step="0.5" min="0" wire:model="hours" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('hours')" class="mt-1" />
            </div>

            <div class="lg:col-span-2">
                <x-input-label for="notes" value="Notes" />
                <x-text-input id="notes" type="text" wire:model="notes" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
            </div>

            <div class="flex items-end lg:col-span-1">
                <button type="submit"
                    class="w-full rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-900/30">
                    Ajouter
                </button>
            </div>
        </div>
    </form>

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
                                {{ $participant->full_name }}
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
                                <button type="button" wire:click="remove({{ $participant->id }})"
                                    wire:confirm="Retirer ce participant de l'activité ?"
                                    class="rounded-lg p-2 text-slate-500 transition hover:bg-red-50 hover:text-red-600"
                                    title="Retirer le participant">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round"
                                            d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                                    </svg>
                                </button>
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
