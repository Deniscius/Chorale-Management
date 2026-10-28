<div>
    @if ($activationLink)
        <x-member-activation-link :link="$activationLink" />
        <div class="mt-5 flex justify-end"><button type="button" wire:click="$dispatch('close-form')" class="btn btn-primary">Terminer</button></div>
    @else
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        @if((bool) $member)<p class="rounded-xl bg-sky-50 p-4 text-sm text-sky-900">Seul le choriste peut modifier ses informations personnelles dans Mon profil. La direction peut gérer sa voix, son pupitre, son statut, sa chorale et sa date d’adhésion.</p>@endif
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="members-form-first_name" value="Prénom" />
                <x-text-input id="members-form-first_name" type="text" name="first_name" aria-describedby="members-form-first_name-error" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" wire:model="first_name" :disabled="(bool) $member" class="mt-1 block w-full" required autofocus />
                <x-input-error id="members-form-first_name-error" :messages="$errors->get('first_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="members-form-last_name" value="Nom" />
                <x-text-input id="members-form-last_name" type="text" name="last_name" aria-describedby="members-form-last_name-error" aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" wire:model="last_name" :disabled="(bool) $member" class="mt-1 block w-full" required />
                <x-input-error id="members-form-last_name-error" :messages="$errors->get('last_name')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="members-form-email" value="Adresse e-mail" />
                <x-text-input id="members-form-email" type="email" name="email" aria-describedby="members-form-email-error" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" wire:model="email" :disabled="(bool) $member" class="mt-1 block w-full" />
                <x-input-error id="members-form-email-error" :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="members-form-phone" value="Téléphone" />
                <x-text-input id="members-form-phone" type="tel" name="phone" aria-describedby="members-form-phone-help members-form-phone-error" aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}" wire:model="phone" :disabled="(bool) $member" class="mt-1 block w-full" />
                <p id="members-form-phone-help" class="mt-1 text-sm text-slate-600">Pour les SMS, utilisez le format international : +228 suivi des 8 chiffres du numéro au Togo.</p>
                <x-input-error id="members-form-phone-error" :messages="$errors->get('phone')" class="mt-1" />
            </div>
        </div>

        <fieldset class="rounded-lg border border-slate-200 px-4 py-3">
            <legend class="px-1 text-xs font-semibold uppercase tracking-wide text-slate-500">
                Rappels de répétition
            </legend>
            <div class="mt-1 flex flex-wrap items-center gap-x-6 gap-y-2">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" aria-describedby="members-form-notify_email-error" aria-invalid="{{ $errors->has('notify_email') ? 'true' : 'false' }}" wire:model="notify_email" @disabled((bool) $member)
                        class="form-checkbox rounded border-slate-300 text-slate-900 focus:ring-slate-500">
                    Recevoir un e-mail
                </label>

                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" aria-describedby="members-form-notify_sms-error" aria-invalid="{{ $errors->has('notify_sms') ? 'true' : 'false' }}" wire:model="notify_sms" @disabled((bool) $member)
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
                <x-input-label for="members-form-voice" value="Voix" />
                <select id="members-form-voice" aria-describedby="members-form-voice-error" aria-invalid="{{ $errors->has('voice') ? 'true' : 'false' }}" wire:model="voice"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->voiceOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="members-form-voice-error" :messages="$errors->get('voice')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="members-form-part" value="Pupitre" />
                <select id="members-form-part" aria-describedby="members-form-part-error" aria-invalid="{{ $errors->has('part') ? 'true' : 'false' }}" wire:model="part"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach (['S1', 'S2', 'A1', 'A2', 'T', 'B'] as $part)
                        <option value="{{ $part }}">{{ $part }}</option>
                    @endforeach
                </select>
                <x-input-error id="members-form-part-error" :messages="$errors->get('part')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="members-form-status" value="Statut" />
                <select id="members-form-status" aria-describedby="members-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="members-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="members-form-choir_id" value="Chorale" />
                @if($this->choirs->count() === 1)
                    <div id="members-form-choir_id" class="form-input mt-1 bg-slate-50 text-slate-700" aria-describedby="members-form-choir_id-help">{{ $this->choirs->first()->name }}</div>
                    <p id="members-form-choir_id-help" class="mt-1 text-xs text-slate-500">La chorale de cet espace est sélectionnée automatiquement.</p>
                @else
                    <select id="members-form-choir_id" aria-describedby="members-form-choir_id-error" aria-invalid="{{ $errors->has('choir_id') ? 'true' : 'false' }}" wire:model="choir_id"
                        class="form-select mt-1">
                        <option value="">—</option>
                        @foreach ($this->choirs as $choir)
                            <option value="{{ $choir->id }}">{{ $choir->name }}</option>
                        @endforeach
                    </select>
                @endif
                <x-input-error id="members-form-choir_id-error" :messages="$errors->get('choir_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="members-form-birth_date" value="Date de naissance" />
                <x-text-input id="members-form-birth_date" type="date" name="birth_date" aria-describedby="members-form-birth_date-error" aria-invalid="{{ $errors->has('birth_date') ? 'true' : 'false' }}" wire:model="birth_date" :disabled="(bool) $member" class="mt-1 block w-full" />
                <x-input-error id="members-form-birth_date-error" :messages="$errors->get('birth_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="members-form-joined_on" value="Date d'adhésion" />
                <x-text-input id="members-form-joined_on" type="date" name="joined_on" aria-describedby="members-form-joined_on-error" aria-invalid="{{ $errors->has('joined_on') ? 'true' : 'false' }}" wire:model="joined_on" class="mt-1 block w-full" />
                <x-input-error id="members-form-joined_on-error" :messages="$errors->get('joined_on')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="members-form-profession" value="Profession" />
                <x-text-input id="members-form-profession" type="text" name="profession" aria-describedby="members-form-profession-error" aria-invalid="{{ $errors->has('profession') ? 'true' : 'false' }}" wire:model="profession" :disabled="(bool) $member" class="mt-1 block w-full" />
                <x-input-error id="members-form-profession-error" :messages="$errors->get('profession')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="members-form-address" value="Adresse" />
                <x-text-input id="members-form-address" type="text" name="address" aria-describedby="members-form-address-error" aria-invalid="{{ $errors->has('address') ? 'true' : 'false' }}" wire:model="address" :disabled="(bool) $member" class="mt-1 block w-full" />
                <x-input-error id="members-form-address-error" :messages="$errors->get('address')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="members-form-notes" value="Notes" />
            <textarea id="members-form-notes" aria-describedby="members-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="members-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
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
    @endif
</div>
