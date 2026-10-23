<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="performances-form-title" value="Intitulé de l'événement" />
                <x-text-input id="performances-form-title" type="text" name="title" aria-describedby="performances-form-title-error" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" wire:model="title" class="mt-1 block w-full" required autofocus />
                <x-input-error id="performances-form-title-error" :messages="$errors->get('title')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="performances-form-type" value="Type d'événement" />
                <select id="performances-form-type" aria-describedby="performances-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="performances-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <x-input-label for="performances-form-scheduled_at" value="Date et heure" />
                <x-text-input id="performances-form-scheduled_at" type="datetime-local" name="scheduled_at" aria-describedby="performances-form-scheduled_at-error" aria-invalid="{{ $errors->has('scheduled_at') ? 'true' : 'false' }}" wire:model="scheduled_at" class="mt-1 block w-full" required />
                <x-input-error id="performances-form-scheduled_at-error" :messages="$errors->get('scheduled_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="performances-form-venue" value="Lieu" />
                <x-text-input id="performances-form-venue" type="text" name="venue" aria-describedby="performances-form-venue-error" aria-invalid="{{ $errors->has('venue') ? 'true' : 'false' }}" wire:model="venue" class="mt-1 block w-full" />
                <x-input-error id="performances-form-venue-error" :messages="$errors->get('venue')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="performances-form-audience" value="Public attendu" />
                <x-text-input id="performances-form-audience" type="text" name="audience" aria-describedby="performances-form-audience-error" aria-invalid="{{ $errors->has('audience') ? 'true' : 'false' }}" wire:model="audience" class="mt-1 block w-full" />
                <x-input-error id="performances-form-audience-error" :messages="$errors->get('audience')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="performances-form-status" value="Statut" />
                <select id="performances-form-status" aria-describedby="performances-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="performances-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="performances-form-budget" value="Budget prévu" />
                <x-text-input id="performances-form-budget" type="number" step="0.01" name="budget" aria-describedby="performances-form-budget-error" aria-invalid="{{ $errors->has('budget') ? 'true' : 'false' }}" wire:model="budget" class="mt-1 block w-full" min="0" />
                <x-input-error id="performances-form-budget-error" :messages="$errors->get('budget')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="performances-form-revenue" value="Recettes escomptées" />
                <x-text-input id="performances-form-revenue" type="number" step="0.01" name="revenue" aria-describedby="performances-form-revenue-error" aria-invalid="{{ $errors->has('revenue') ? 'true' : 'false' }}" wire:model="revenue" class="mt-1 block w-full" min="0" />
                <x-input-error id="performances-form-revenue-error" :messages="$errors->get('revenue')" class="mt-1" />
            </div>
        </div>

        <div>
            <p id="performances-program-label" class="form-label">Œuvres au programme</p>
            <div role="group" aria-labelledby="performances-program-label" class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-300 p-2">
                @forelse ($this->availableWorks as $work)
                    <label class="flex items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                        <input type="checkbox" value="{{ $work->id }}" wire:model="works"
                            class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
                        <span class="flex-1">{{ $work->title }}</span>
                        <span class="text-xs text-slate-400">{{ $work->duration_minutes ? $work->duration_minutes.' min' : '' }}</span>
                    </label>
                @empty
                    <p class="px-2 py-3 text-center text-sm text-slate-500">Aucune œuvre au répertoire.</p>
                @endforelse
            </div>
            <x-input-error id="performances-form-works-error" :messages="$errors->get('works')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="performances-form-notes" value="Notes" />
            <textarea id="performances-form-notes" aria-describedby="performances-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="performances-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
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
