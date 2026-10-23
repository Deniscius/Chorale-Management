<div>
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="date" value="Date" />
                <x-text-input id="date" type="date" wire:model="date" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="starts_at" value="Heure de début" />
                <x-text-input id="starts_at" type="time" wire:model="starts_at" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="ends_at" value="Heure de fin" />
                <x-text-input id="ends_at" type="time" wire:model="ends_at" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('ends_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="venue" value="Lieu" />
                <x-text-input id="venue" type="text" wire:model="venue" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('venue')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="type" value="Type de séance" />
                <select id="type" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
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

        <div>
            <x-input-label for="works" value="Œuvres au programme" />
            <div class="mt-1 max-h-56 overflow-y-auto rounded-lg border border-slate-300 p-2">
                @forelse ($this->availableWorks as $work)
                    <label class="flex items-center gap-2 rounded px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                        <input type="checkbox" value="{{ $work->id }}" wire:model="works"
                            class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500">
                        <span class="flex-1">{{ $work->title }}</span>
                        <span class="text-xs text-slate-400">{{ $work->type->label() }}</span>
                    </label>
                @empty
                    <p class="px-2 py-3 text-center text-sm text-slate-500">Aucune œuvre au répertoire.</p>
                @endforelse
            </div>
            <x-input-error :messages="$errors->get('works')" class="mt-1" />
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
