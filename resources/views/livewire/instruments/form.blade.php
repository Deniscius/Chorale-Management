<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <x-input-label for="name" value="Désignation" />
                <x-text-input id="name" type="text" wire:model="name" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="category" value="Catégorie" />
                <select id="category" wire:model="category"
                    class="form-select mt-1">
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="serial_number" value="Numéro de série" />
                <x-text-input id="serial_number" type="text" wire:model="serial_number" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('serial_number')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="quantity" value="Quantité" />
                <x-text-input id="quantity" type="number" wire:model="quantity" class="mt-1 block w-full" min="1" />
                <x-input-error :messages="$errors->get('quantity')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="condition" value="État" />
                <select id="condition" wire:model="condition"
                    class="form-select mt-1">
                    @foreach ($this->conditionOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('condition')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="purchase_on" value="Date d'acquisition" />
                <x-text-input id="purchase_on" type="date" wire:model="purchase_on" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('purchase_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="purchase_price" value="Prix d'achat" />
                <x-text-input id="purchase_price" type="number" step="0.01" wire:model="purchase_price" class="mt-1 block w-full" min="0" />
                <x-input-error :messages="$errors->get('purchase_price')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="current_value" value="Valeur actuelle" />
                <x-text-input id="current_value" type="number" step="0.01" wire:model="current_value" class="mt-1 block w-full" min="0" />
                <x-input-error :messages="$errors->get('current_value')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="location" value="Emplacement" />
                <x-text-input id="location" type="text" wire:model="location" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('location')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="custodian_id" value="Responsable" />
                <select id="custodian_id" wire:model="custodian_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->custodians as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('custodian_id')" class="mt-1" />
            </div>
        </div>

        <label class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
            <input type="checkbox" wire:model="is_borrowed"
                class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
            <span class="text-sm font-medium text-slate-700">Instrument actuellement sorti ou prêté</span>
        </label>

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
