<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <x-input name="quantity" label="Quantité déplacée" type="number" min="1" wire:model="quantity" />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instrument_id" value="Instrument" />
                <select id="instrument_id" wire:model="instrument_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->instruments as $instrument)
                        <option value="{{ $instrument->id }}">{{ $instrument->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('instrument_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="type" value="Type de mouvement" />
                <select id="type" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="moved_on" value="Date du mouvement" />
                <x-text-input id="moved_on" type="date" wire:model="moved_on" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('moved_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="responsible_id" value="Responsable" />
                <select id="responsible_id" wire:model="responsible_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->responsibles as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('responsible_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="reason" value="Motif" />
                <x-text-input id="reason" type="text" wire:model="reason" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('reason')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="destination" value="Destination" />
                <x-text-input id="destination" type="text" wire:model="destination" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('destination')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="expected_return_on" value="Retour prévu le" />
                <x-text-input id="expected_return_on" type="date" wire:model="expected_return_on" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('expected_return_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="actual_return_on" value="Retour effectif le" />
                <x-text-input id="actual_return_on" type="date" wire:model="actual_return_on" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('actual_return_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="condition_on_return" value="État au retour" />
                <select id="condition_on_return" wire:model="condition_on_return"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->conditionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('condition_on_return')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
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
