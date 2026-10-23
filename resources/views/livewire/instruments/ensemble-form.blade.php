<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="instruments-ensemble-form-name" value="Nom de l'ensemble" />
                <x-text-input id="instruments-ensemble-form-name" type="text" name="name" aria-describedby="instruments-ensemble-form-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" wire:model="name" class="mt-1 block w-full" required autofocus />
                <x-input-error id="instruments-ensemble-form-name-error" :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="instruments-ensemble-form-conductor_id" value="Chef d'ensemble" />
                <select id="instruments-ensemble-form-conductor_id" aria-describedby="instruments-ensemble-form-conductor_id-error" aria-invalid="{{ $errors->has('conductor_id') ? 'true' : 'false' }}" wire:model="conductor_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->conductors as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error id="instruments-ensemble-form-conductor_id-error" :messages="$errors->get('conductor_id')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="instruments-ensemble-form-member_count" value="Effectif" />
            <x-text-input id="instruments-ensemble-form-member_count" type="number" name="member_count" aria-describedby="instruments-ensemble-form-member_count-error" aria-invalid="{{ $errors->has('member_count') ? 'true' : 'false' }}" wire:model="member_count" class="mt-1 block w-full" min="0" />
            <x-input-error id="instruments-ensemble-form-member_count-error" :messages="$errors->get('member_count')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="instruments-ensemble-form-description" value="Description" />
            <textarea id="instruments-ensemble-form-description" aria-describedby="instruments-ensemble-form-description-error" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" wire:model="description" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="instruments-ensemble-form-description-error" :messages="$errors->get('description')" class="mt-1" />
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
