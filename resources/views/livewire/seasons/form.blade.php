<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="seasons-form-name" value="Nom du temps liturgique" />
                <x-text-input id="seasons-form-name" type="text" name="name" aria-describedby="seasons-form-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" wire:model="name" class="mt-1 block w-full" required autofocus />
                <x-input-error id="seasons-form-name-error" :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="seasons-form-color" value="Couleur liturgique" />
                <select id="seasons-form-color" aria-describedby="seasons-form-color-error" aria-invalid="{{ $errors->has('color') ? 'true' : 'false' }}" wire:model="color"
                    class="form-select mt-1">
                    @foreach ($colors as $color)
                        <option value="{{ $color }}">{{ ucfirst($color) }}</option>
                    @endforeach
                </select>
                <x-input-error id="seasons-form-color-error" :messages="$errors->get('color')" class="mt-1" />
                <span class="mt-2 inline-flex items-center gap-2 text-xs text-slate-500">
                    <span class="h-3 w-3 rounded-full bg-{{ $color }}-500"></span>
                    Aperçu de la couleur sélectionnée
                </span>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="seasons-form-starts_on" value="Date de début" />
                <x-text-input id="seasons-form-starts_on" type="date" name="starts_on" aria-describedby="seasons-form-starts_on-error" aria-invalid="{{ $errors->has('starts_on') ? 'true' : 'false' }}" wire:model="starts_on" class="mt-1 block w-full" />
                <x-input-error id="seasons-form-starts_on-error" :messages="$errors->get('starts_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="seasons-form-ends_on" value="Date de fin" />
                <x-text-input id="seasons-form-ends_on" type="date" name="ends_on" aria-describedby="seasons-form-ends_on-error" aria-invalid="{{ $errors->has('ends_on') ? 'true' : 'false' }}" wire:model="ends_on" class="mt-1 block w-full" />
                <x-input-error id="seasons-form-ends_on-error" :messages="$errors->get('ends_on')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="seasons-form-description" value="Description" />
            <textarea id="seasons-form-description" aria-describedby="seasons-form-description-error" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" wire:model="description" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="seasons-form-description-error" :messages="$errors->get('description')" class="mt-1" />
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
