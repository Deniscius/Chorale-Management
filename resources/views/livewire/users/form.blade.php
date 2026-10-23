<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="users-form-name" value="Nom complet" />
                <x-text-input id="users-form-name" type="text" name="name" aria-describedby="users-form-name-error" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" wire:model="name" class="mt-1 block w-full" required autofocus />
                <x-input-error id="users-form-name-error" :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="users-form-username" value="Identifiant de connexion" />
                <x-text-input id="users-form-username" type="text" name="username" aria-describedby="users-form-username-error" aria-invalid="{{ $errors->has('username') ? 'true' : 'false' }}" wire:model="username" class="mt-1 block w-full"
                    autocapitalize="none" spellcheck="false" required />
                <x-input-error id="users-form-username-error" :messages="$errors->get('username')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">
                    Lettres, chiffres, points, tirets et underscores. C'est cet identifiant qui sert à se connecter.
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="users-form-email" value="Adresse e-mail" />
                <x-text-input id="users-form-email" type="email" name="email" aria-describedby="users-form-email-error" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" wire:model="email" class="mt-1 block w-full" required />
                <x-input-error id="users-form-email-error" :messages="$errors->get('email')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">Utilisée pour les rappels et les notifications.</p>
            </div>

            <div>
                <x-input-label for="users-form-role" value="Rôle" />
                <select id="users-form-role" aria-describedby="users-form-role-error" aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}" wire:model="role"
                    class="form-select mt-1">
                    @foreach ($this->roleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="users-form-role-error" :messages="$errors->get('role')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">
                    Le rôle détermine les modules consultables et les modules modifiables.
                </p>
            </div>
        </div>

        <div>
            <x-input-label for="users-form-member_id" value="Choriste rattaché" />
            <select id="users-form-member_id" aria-describedby="users-form-member_id-error" aria-invalid="{{ $errors->has('member_id') ? 'true' : 'false' }}" wire:model="member_id"
                class="form-select mt-1">
                <option value="">—</option>
                @foreach ($this->availableMembers as $member)
                    <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                @endforeach
            </select>
            <x-input-error id="users-form-member_id-error" :messages="$errors->get('member_id')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="users-form-password" :value="$user ? 'Nouveau mot de passe' : 'Mot de passe'" />
                <x-text-input id="users-form-password" type="password" name="password" aria-describedby="users-form-password-error" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" wire:model="password" class="mt-1 block w-full"
                    autocomplete="new-password" />
                <x-input-error id="users-form-password-error" :messages="$errors->get('password')" class="mt-1" />
                @if ($user)
                    <p class="mt-1 text-xs text-slate-500">Laissez vide pour conserver le mot de passe actuel.</p>
                @endif
            </div>

            <div>
                <x-input-label for="users-form-password_confirmation" value="Confirmer le mot de passe" />
                <x-text-input id="users-form-password_confirmation" type="password" name="password_confirmation" aria-describedby="users-form-password_confirmation-error" aria-invalid="{{ $errors->has('password_confirmation') ? 'true' : 'false' }}" wire:model="password_confirmation"
                    class="mt-1 block w-full" autocomplete="new-password" />
                <x-input-error id="users-form-password_confirmation-error" :messages="$errors->get('password_confirmation')" class="mt-1" />
            </div>
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
