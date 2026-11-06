<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="rehearsals-form-date" value="Date" />
                <x-text-input id="rehearsals-form-date" type="date" name="date" aria-describedby="rehearsals-form-date-error" aria-invalid="{{ $errors->has('date') ? 'true' : 'false' }}" wire:model="date" class="mt-1 block w-full" required />
                <div class="mt-2 flex flex-wrap gap-2" aria-label="Dates proposées">
                    <button type="button" wire:click="useSuggestedDate('today')" class="btn btn-soft btn-sm">Aujourd’hui</button>
                    <button type="button" wire:click="useSuggestedDate('tomorrow')" class="btn btn-soft btn-sm">Demain</button>
                    <button type="button" wire:click="useSuggestedDate('next_week')" class="btn btn-soft btn-sm">Dans 7 jours</button>
                </div>
                <x-input-error id="rehearsals-form-date-error" :messages="$errors->get('date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="rehearsals-form-starts_at" value="Heure de début" />
                <x-text-input id="rehearsals-form-starts_at" type="time" name="starts_at" aria-describedby="rehearsals-form-starts_at-error" aria-invalid="{{ $errors->has('starts_at') ? 'true' : 'false' }}" wire:model="starts_at" class="mt-1 block w-full" />
                <x-input-error id="rehearsals-form-starts_at-error" :messages="$errors->get('starts_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="rehearsals-form-ends_at" value="Heure de fin" />
                <x-text-input id="rehearsals-form-ends_at" type="time" name="ends_at" aria-describedby="rehearsals-form-ends_at-error" aria-invalid="{{ $errors->has('ends_at') ? 'true' : 'false' }}" wire:model="ends_at" class="mt-1 block w-full" />
                <x-input-error id="rehearsals-form-ends_at-error" :messages="$errors->get('ends_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="rehearsals-form-venue" value="Lieu" />
                <x-text-input id="rehearsals-form-venue" type="text" name="venue" aria-describedby="rehearsals-form-venue-error" aria-invalid="{{ $errors->has('venue') ? 'true' : 'false' }}" wire:model="venue" class="mt-1 block w-full" />
                <x-input-error id="rehearsals-form-venue-error" :messages="$errors->get('venue')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="rehearsals-form-type" value="Type de séance" />
                <select id="rehearsals-form-type" aria-describedby="rehearsals-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="rehearsals-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="rehearsals-form-status" value="Statut" />
                <select id="rehearsals-form-status" aria-describedby="rehearsals-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="rehearsals-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>
        </div>

        <label class="flex items-start gap-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
            <input type="checkbox" wire:model="is_special" class="mt-1 rounded border-slate-300">
            <span><strong>Répétition spéciale ou exceptionnelle</strong><span class="block text-xs text-slate-600">Cette séance reste indépendante des créneaux habituels. Vous pouvez informer les choristes depuis la liste après enregistrement.</span></span>
        </label>

        <div>
            <p id="rehearsals-program-label" class="form-label">Œuvres au programme</p>
            <div role="group" aria-labelledby="rehearsals-program-label" class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-300 p-2">
                @forelse ($this->availableWorks as $work)
                    <label class="flex items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                        <input type="checkbox" value="{{ $work->id }}" wire:model="works"
                            class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
                        <span class="flex-1">{{ $work->title }}</span>
                        <span class="text-xs text-slate-400">{{ $work->type->label() }}</span>
                    </label>
                @empty
                    <p class="px-2 py-3 text-center text-sm text-slate-500">Aucune œuvre au répertoire.</p>
                @endforelse
            </div>
            <x-input-error id="rehearsals-form-works-error" :messages="$errors->get('works')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="rehearsals-form-notes" value="Notes" />
            <textarea id="rehearsals-form-notes" aria-describedby="rehearsals-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="rehearsals-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" wire:click="$dispatch('close-form')"
                class="btn btn-secondary">
                Annuler
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
