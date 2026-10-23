<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <x-input-label for="instruments-form-name" value="Désignation" />
                <x-text-input id="instruments-form-name" type="text" name="name" aria-describedby="instruments-form-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" wire:model="name" class="mt-1 block w-full" required autofocus />
                <x-input-error id="instruments-form-name-error" :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-category" value="Catégorie" />
                <select id="instruments-form-category" aria-describedby="instruments-form-category-error" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" wire:model="category"
                    class="form-select mt-1">
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-form-category-error" :messages="$errors->get('category')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="instruments-form-serial_number" value="Numéro de série" />
                <x-text-input id="instruments-form-serial_number" type="text" name="serial_number" aria-describedby="instruments-form-serial_number-error" aria-invalid="{{ $errors->has('serial_number') ? 'true' : 'false' }}" wire:model="serial_number" class="mt-1 block w-full" />
                <x-input-error id="instruments-form-serial_number-error" :messages="$errors->get('serial_number')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-quantity" value="Quantité" />
                <x-text-input id="instruments-form-quantity" type="number" name="quantity" aria-describedby="instruments-form-quantity-error" aria-invalid="{{ $errors->has('quantity') ? 'true' : 'false' }}" wire:model="quantity" class="mt-1 block w-full" min="1" />
                <x-input-error id="instruments-form-quantity-error" :messages="$errors->get('quantity')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-condition" value="État" />
                <select id="instruments-form-condition" aria-describedby="instruments-form-condition-error" aria-invalid="{{ $errors->has('condition') ? 'true' : 'false' }}" wire:model="condition"
                    class="form-select mt-1">
                    @foreach ($this->conditionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-form-condition-error" :messages="$errors->get('condition')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="instruments-form-purchase_on" value="Date d'acquisition" />
                <x-text-input id="instruments-form-purchase_on" type="date" name="purchase_on" aria-describedby="instruments-form-purchase_on-error" aria-invalid="{{ $errors->has('purchase_on') ? 'true' : 'false' }}" wire:model="purchase_on" class="mt-1 block w-full" />
                <x-input-error id="instruments-form-purchase_on-error" :messages="$errors->get('purchase_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-purchase_price" value="Prix d'achat" />
                <x-text-input id="instruments-form-purchase_price" type="number" step="0.01" name="purchase_price" aria-describedby="instruments-form-purchase_price-error" aria-invalid="{{ $errors->has('purchase_price') ? 'true' : 'false' }}" wire:model="purchase_price" class="mt-1 block w-full" min="0" />
                <x-input-error id="instruments-form-purchase_price-error" :messages="$errors->get('purchase_price')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-current_value" value="Valeur actuelle" />
                <x-text-input id="instruments-form-current_value" type="number" step="0.01" name="current_value" aria-describedby="instruments-form-current_value-error" aria-invalid="{{ $errors->has('current_value') ? 'true' : 'false' }}" wire:model="current_value" class="mt-1 block w-full" min="0" />
                <x-input-error id="instruments-form-current_value-error" :messages="$errors->get('current_value')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instruments-form-location" value="Emplacement" />
                <x-text-input id="instruments-form-location" type="text" name="location" aria-describedby="instruments-form-location-error" aria-invalid="{{ $errors->has('location') ? 'true' : 'false' }}" wire:model="location" class="mt-1 block w-full" />
                <x-input-error id="instruments-form-location-error" :messages="$errors->get('location')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-form-custodian_id" value="Responsable" />
                <select id="instruments-form-custodian_id" aria-describedby="instruments-form-custodian_id-error" aria-invalid="{{ $errors->has('custodian_id') ? 'true' : 'false' }}" wire:model="custodian_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->custodians as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-form-custodian_id-error" :messages="$errors->get('custodian_id')" class="mt-1" />
            </div>
        </div>

        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <input type="checkbox" disabled @checked($is_borrowed) title="Disponibilité calculée depuis les mouvements"
                class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
            <span class="text-sm font-medium text-slate-700">Instrument actuellement sorti ou prêté</span>
        </label>

        <div>
            <x-input-label for="instruments-form-notes" value="Notes" />
            <textarea id="instruments-form-notes" aria-describedby="instruments-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="instruments-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
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
