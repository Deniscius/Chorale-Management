<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <x-input-label for="activities-form-title" value="Intitulé" />
                <x-text-input id="activities-form-title" type="text" name="title" aria-describedby="activities-form-title-error" aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" wire:model="title" class="mt-1 block w-full" required autofocus />
                <x-input-error id="activities-form-title-error" :messages="$errors->get('title')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-form-type" value="Type d'activité" />
                <select id="activities-form-type" aria-describedby="activities-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="activities-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-form-status" value="Statut" />
                <select id="activities-form-status" aria-describedby="activities-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="activities-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="activities-form-description" value="Description" />
            <textarea id="activities-form-description" aria-describedby="activities-form-description-error" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" wire:model="description" rows="3"
                class="form-textarea mt-1"
                placeholder="Objectifs, déroulé, informations pratiques…"></textarea>
            <x-input-error id="activities-form-description-error" :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="activities-form-starts_at" value="Début" />
                <x-text-input id="activities-form-starts_at" type="datetime-local" name="starts_at" aria-describedby="activities-form-starts_at-error" aria-invalid="{{ $errors->has('starts_at') ? 'true' : 'false' }}" wire:model="starts_at" class="mt-1 block w-full" required />
                <x-input-error id="activities-form-starts_at-error" :messages="$errors->get('starts_at')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-form-ends_at" value="Fin" />
                <x-text-input id="activities-form-ends_at" type="datetime-local" name="ends_at" aria-describedby="activities-form-ends_at-error" aria-invalid="{{ $errors->has('ends_at') ? 'true' : 'false' }}" wire:model="ends_at" class="mt-1 block w-full" />
                <x-input-error id="activities-form-ends_at-error" :messages="$errors->get('ends_at')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="activities-form-venue" value="Lieu" />
                <x-text-input id="activities-form-venue" type="text" name="venue" aria-describedby="activities-form-venue-error" aria-invalid="{{ $errors->has('venue') ? 'true' : 'false' }}" wire:model="venue" class="mt-1 block w-full" />
                <x-input-error id="activities-form-venue-error" :messages="$errors->get('venue')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-form-choir_commission_id" value="Commission organisatrice" />
                <select id="activities-form-choir_commission_id" aria-describedby="activities-form-choir_commission_id-help activities-form-choir_commission_id-error" aria-invalid="{{ $errors->has('choir_commission_id') ? 'true' : 'false' }}" wire:model="choir_commission_id"
                    class="form-select mt-1">
                    <option value="">Choisir une commission</option>
                    @foreach ($this->commissions as $commission)
                        <option value="{{ $commission->id }}">{{ $commission->name }}</option>
                    @endforeach
                </select>
                <p id="activities-form-choir_commission_id-help" class="mt-1 text-xs text-slate-600">La commission porte l’organisation ; ses membres peuvent ensuite être ajoutés comme participants.</p>
                <x-input-error id="activities-form-choir_commission_id-error" :messages="$errors->get('choir_commission_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-form-choir_id" value="Chorale" />
                <select id="activities-form-choir_id" aria-describedby="activities-form-choir_id-error" aria-invalid="{{ $errors->has('choir_id') ? 'true' : 'false' }}" wire:model="choir_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->choirs as $choir)
                        <option value="{{ $choir->id }}">{{ $choir->name }}</option>
                    @endforeach
                </select>
                <x-input-error id="activities-form-choir_id-error" :messages="$errors->get('choir_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="activities-form-budget_allocated" value="Budget alloué (FCFA)" />
                <x-text-input id="activities-form-budget_allocated" type="number" step="0.01" min="0" name="budget_allocated" aria-describedby="activities-form-budget_allocated-error" aria-invalid="{{ $errors->has('budget_allocated') ? 'true' : 'false' }}" wire:model="budget_allocated"
                    class="mt-1 block w-full" />
                <x-input-error id="activities-form-budget_allocated-error" :messages="$errors->get('budget_allocated')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activities-form-budget_spent" value="Budget engagé (FCFA)" />
                <x-text-input id="activities-form-budget_spent" type="number" step="0.01" min="0" name="budget_spent" aria-describedby="activities-form-budget_spent-error" aria-invalid="{{ $errors->has('budget_spent') ? 'true' : 'false' }}" wire:model="budget_spent"
                    class="mt-1 block w-full" />
                <x-input-error id="activities-form-budget_spent-error" :messages="$errors->get('budget_spent')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="activities-form-report" value="Rapport / compte rendu" />
            <textarea id="activities-form-report" aria-describedby="activities-form-report-error" aria-invalid="{{ $errors->has('report') ? 'true' : 'false' }}" wire:model="report" rows="3"
                class="form-textarea mt-1"
                placeholder="Bilan, participants, incidents…"></textarea>
            <x-input-error id="activities-form-report-error" :messages="$errors->get('report')" class="mt-1" />
        </div>

        <label class="flex items-center gap-2">
            <input type="checkbox" aria-describedby="activities-form-is_public-error" aria-invalid="{{ $errors->has('is_public') ? 'true' : 'false' }}" wire:model="is_public"
                class="rounded border-slate-300 text-slate-900 focus:ring-slate-500">
            <span class="text-sm text-slate-700">Activité publiée (visible par les choristes)</span>
        </label>

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
