<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="first_name" value="Prénom" />
                <x-text-input id="first_name" type="text" wire:model="first_name" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('first_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="last_name" value="Nom" />
                <x-text-input id="last_name" type="text" wire:model="last_name" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('last_name')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="email" value="Adresse e-mail" />
                <x-text-input id="email" type="email" wire:model="email" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="phone" value="Téléphone" />
                <x-text-input id="phone" type="text" wire:model="phone" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>
        </div>

        <fieldset class="rounded-lg border border-slate-200 px-4 py-3">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Rappels de répétition
            </legend>
            <div class="mt-1 flex flex-wrap items-center gap-x-6 gap-y-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="notify_email"
                        class="form-checkbox rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    Recevoir un e-mail
                </label>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" wire:model="notify_sms"
                        class="form-checkbox rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    Recevoir un SMS
                </label>
            </div>
            <p class="mt-2 text-xs text-slate-500">
                Un canal n’est utilisé que si la coordonnée correspondante est renseignée.
            </p>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="voice" value="Voix" />
                <select id="voice" wire:model="voice"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->voiceOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('voice')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="part" value="Pupitre" />
                <select id="part" wire:model="part"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach (['S1', 'S2', 'A1', 'A2', 'T', 'B'] as $part)
                        <option value="{{ $part }}">{{ $part }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('part')" class="mt-1" />
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

        <div class="grid gap-4 sm:grid-cols-3">
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

            <div>
                <x-input-label for="birth_date" value="Date de naissance" />
                <x-text-input id="birth_date" type="date" wire:model="birth_date" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('birth_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="joined_on" value="Date d'adhésion" />
                <x-text-input id="joined_on" type="date" wire:model="joined_on" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('joined_on')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="profession" value="Profession" />
                <x-text-input id="profession" type="text" wire:model="profession" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('profession')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="address" value="Adresse" />
                <x-text-input id="address" type="text" wire:model="address" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('address')" class="mt-1" />
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
