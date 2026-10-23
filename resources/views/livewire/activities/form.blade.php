<div>
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="title" value="Intitulé" />
                <x-text-input id="title" type="text" wire:model="title" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('title')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="type" value="Type d'activité" />
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
            <x-input-label for="description" value="Description" />
            <textarea id="description" wire:model="description" rows="3"
                class="form-textarea mt-1"
                placeholder="Objectifs, déroulé, informations pratiques…"></textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="starts_at" value="Début" />
                <x-text-input id="starts_at" type="datetime-local" wire:model="starts_at" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('starts_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="ends_at" value="Fin" />
                <x-text-input id="ends_at" type="datetime-local" wire:model="ends_at" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('ends_at')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="venue" value="Lieu" />
                <x-text-input id="venue" type="text" wire:model="venue" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('venue')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="organizer_id" value="Organisateur" />
                <select id="organizer_id" wire:model="organizer_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->organizers as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('organizer_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="choir_id" value="Chorale" />
                <select id="choir_id" wire:model="choir_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->choirs as $choir)
                        <option value="{{ $choir->id }}">{{ $choir->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('choir_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="budget_allocated" value="Budget alloué (FCFA)" />
                <x-text-input id="budget_allocated" type="number" step="0.01" min="0" wire:model="budget_allocated"
                    class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('budget_allocated')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="budget_spent" value="Budget engagé (FCFA)" />
                <x-text-input id="budget_spent" type="number" step="0.01" min="0" wire:model="budget_spent"
                    class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('budget_spent')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="report" value="Rapport / compte rendu" />
            <textarea id="report" wire:model="report" rows="3"
                class="form-textarea mt-1"
                placeholder="Bilan, participants, incidents…"></textarea>
            <x-input-error :messages="$errors->get('report')" class="mt-1" />
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" wire:model="is_public"
                class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
            <span class="text-sm text-slate-700">Activité publiée (visible par les choristes)</span>
        </label>

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
