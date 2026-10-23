<div>
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="name" value="Nom de l'ensemble" />
                <x-text-input id="name" type="text" wire:model="name" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="conductor_id" value="Chef d'ensemble" />
                <select id="conductor_id" wire:model="conductor_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->conductors as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('conductor_id')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="member_count" value="Effectif" />
            <x-text-input id="member_count" type="number" wire:model="member_count" class="mt-1 block w-full" min="0" />
            <x-input-error :messages="$errors->get('member_count')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="description" value="Description" />
            <textarea id="description" wire:model="description" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
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
