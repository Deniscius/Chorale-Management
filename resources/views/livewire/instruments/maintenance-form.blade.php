<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instruments-maintenance-form-instrument_id" value="Instrument" />
                <select id="instruments-maintenance-form-instrument_id" aria-describedby="instruments-maintenance-form-instrument_id-error" aria-invalid="{{ $errors->has('instrument_id') ? 'true' : 'false' }}" wire:model="instrument_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->instruments as $instrument)
                        <option value="{{ $instrument->id }}">{{ $instrument->name }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-maintenance-form-instrument_id-error" :messages="$errors->get('instrument_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-maintenance-form-type" value="Type d'intervention" />
                <select id="instruments-maintenance-form-type" aria-describedby="instruments-maintenance-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-maintenance-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="instruments-maintenance-form-description" value="Description de l'intervention" />
            <textarea id="instruments-maintenance-form-description" aria-describedby="instruments-maintenance-form-description-error" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" wire:model="description" rows="3"
                class="form-textarea mt-1"
                placeholder="Opérations réalisées, pièces remplacées, observations…"></textarea>
            <x-input-error id="instruments-maintenance-form-description-error" :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="instruments-maintenance-form-provider" value="Prestataire" />
                <x-text-input id="instruments-maintenance-form-provider" type="text" name="provider" aria-describedby="instruments-maintenance-form-provider-error" aria-invalid="{{ $errors->has('provider') ? 'true' : 'false' }}" wire:model="provider" class="mt-1 block w-full" />
                <x-input-error id="instruments-maintenance-form-provider-error" :messages="$errors->get('provider')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-maintenance-form-cost" value="Coût" />
                <x-text-input id="instruments-maintenance-form-cost" type="number" step="0.01" name="cost" aria-describedby="instruments-maintenance-form-cost-error" aria-invalid="{{ $errors->has('cost') ? 'true' : 'false' }}" wire:model="cost" class="mt-1 block w-full" min="0" />
                <x-input-error id="instruments-maintenance-form-cost-error" :messages="$errors->get('cost')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-maintenance-form-status" value="Statut" />
                <select id="instruments-maintenance-form-status" aria-describedby="instruments-maintenance-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-maintenance-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instruments-maintenance-form-scheduled_for" value="Prévue le" />
                <x-text-input id="instruments-maintenance-form-scheduled_for" type="date" name="scheduled_for" aria-describedby="instruments-maintenance-form-scheduled_for-error" aria-invalid="{{ $errors->has('scheduled_for') ? 'true' : 'false' }}" wire:model="scheduled_for" class="mt-1 block w-full" />
                <x-input-error id="instruments-maintenance-form-scheduled_for-error" :messages="$errors->get('scheduled_for')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-maintenance-form-performed_on" value="Effectuée le" />
                <x-text-input id="instruments-maintenance-form-performed_on" type="date" name="performed_on" aria-describedby="instruments-maintenance-form-performed_on-error" aria-invalid="{{ $errors->has('performed_on') ? 'true' : 'false' }}" wire:model="performed_on" class="mt-1 block w-full" />
                <x-input-error id="instruments-maintenance-form-performed_on-error" :messages="$errors->get('performed_on')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="instruments-maintenance-form-notes" value="Notes" />
            <textarea id="instruments-maintenance-form-notes" aria-describedby="instruments-maintenance-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="instruments-maintenance-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
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
