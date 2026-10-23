<div>
    <form wire:submit="save" class="space-y-5">
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
                <x-input-label for="type" value="Type d'intervention" />
                <select id="type" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="description" value="Description de l'intervention" />
            <textarea id="description" wire:model="description" rows="3"
                class="form-textarea mt-1"
                placeholder="Opérations réalisées, pièces remplacées, observations…"></textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="provider" value="Prestataire" />
                <x-text-input id="provider" type="text" wire:model="provider" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('provider')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="cost" value="Coût" />
                <x-text-input id="cost" type="number" step="0.01" wire:model="cost" class="mt-1 block w-full" min="0" />
                <x-input-error :messages="$errors->get('cost')" class="mt-1" />
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
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="scheduled_for" value="Prévue le" />
                <x-text-input id="scheduled_for" type="date" wire:model="scheduled_for" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('scheduled_for')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="performed_on" value="Effectuée le" />
                <x-text-input id="performed_on" type="date" wire:model="performed_on" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('performed_on')" class="mt-1" />
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
            <button type="submit"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
