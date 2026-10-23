<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <x-input id="instruments-movement-form-quantity" name="quantity" label="Quantité déplacée" type="number" min="1" wire:model="quantity" />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instruments-movement-form-instrument_id" value="Instrument" />
                <select id="instruments-movement-form-instrument_id" aria-describedby="instruments-movement-form-instrument_id-error" aria-invalid="{{ $errors->has('instrument_id') ? 'true' : 'false' }}" wire:model="instrument_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->instruments as $instrument)
                        <option value="{{ $instrument->id }}">{{ $instrument->name }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-movement-form-instrument_id-error" :messages="$errors->get('instrument_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-movement-form-type" value="Type de mouvement" />
                <select id="instruments-movement-form-type" aria-describedby="instruments-movement-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-movement-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instruments-movement-form-moved_on" value="Date du mouvement" />
                <x-text-input id="instruments-movement-form-moved_on" type="date" name="moved_on" aria-describedby="instruments-movement-form-moved_on-error" aria-invalid="{{ $errors->has('moved_on') ? 'true' : 'false' }}" wire:model="moved_on" class="mt-1 block w-full" required />
                <x-input-error id="instruments-movement-form-moved_on-error" :messages="$errors->get('moved_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-movement-form-responsible_id" value="Responsable" />
                <select id="instruments-movement-form-responsible_id" aria-describedby="instruments-movement-form-responsible_id-error" aria-invalid="{{ $errors->has('responsible_id') ? 'true' : 'false' }}" wire:model="responsible_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->responsibles as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-movement-form-responsible_id-error" :messages="$errors->get('responsible_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instruments-movement-form-reason" value="Motif" />
                <x-text-input id="instruments-movement-form-reason" type="text" name="reason" aria-describedby="instruments-movement-form-reason-error" aria-invalid="{{ $errors->has('reason') ? 'true' : 'false' }}" wire:model="reason" class="mt-1 block w-full" />
                <x-input-error id="instruments-movement-form-reason-error" :messages="$errors->get('reason')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-movement-form-destination" value="Destination" />
                <x-text-input id="instruments-movement-form-destination" type="text" name="destination" aria-describedby="instruments-movement-form-destination-error" aria-invalid="{{ $errors->has('destination') ? 'true' : 'false' }}" wire:model="destination" class="mt-1 block w-full" />
                <x-input-error id="instruments-movement-form-destination-error" :messages="$errors->get('destination')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="instruments-movement-form-expected_return_on" value="Retour prévu le" />
                <x-text-input id="instruments-movement-form-expected_return_on" type="date" name="expected_return_on" aria-describedby="instruments-movement-form-expected_return_on-error" aria-invalid="{{ $errors->has('expected_return_on') ? 'true' : 'false' }}" wire:model="expected_return_on" class="mt-1 block w-full" />
                <x-input-error id="instruments-movement-form-expected_return_on-error" :messages="$errors->get('expected_return_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-movement-form-actual_return_on" value="Retour effectif le" />
                <x-text-input id="instruments-movement-form-actual_return_on" type="date" name="actual_return_on" aria-describedby="instruments-movement-form-actual_return_on-error" aria-invalid="{{ $errors->has('actual_return_on') ? 'true' : 'false' }}" wire:model="actual_return_on" class="mt-1 block w-full" />
                <x-input-error id="instruments-movement-form-actual_return_on-error" :messages="$errors->get('actual_return_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-movement-form-condition_on_return" value="État au retour" />
                <select id="instruments-movement-form-condition_on_return" aria-describedby="instruments-movement-form-condition_on_return-error" aria-invalid="{{ $errors->has('condition_on_return') ? 'true' : 'false' }}" wire:model="condition_on_return"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->conditionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-movement-form-condition_on_return-error" :messages="$errors->get('condition_on_return')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="instruments-movement-form-notes" value="Notes" />
            <textarea id="instruments-movement-form-notes" aria-describedby="instruments-movement-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="instruments-movement-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
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
