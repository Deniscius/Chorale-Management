<div>
    <form wire:submit="save" class="space-y-5">
        <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
            <p class="font-medium text-slate-800">{{ $member->full_name }}</p>
            <p class="mt-0.5 text-xs text-slate-500">
                @if ($this->existingAccount)
                    Compte existant · créé le {{ $this->existingAccount->created_at?->format('d/m/Y') }}
                @else
                    Aucun compte d'accès : l'enregistrement créera le compte et générera un mot de passe aléatoire.
                @endif
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="role" value="Rôle attribué" />
                <select id="role" wire:model="role"
                    class="form-select mt-1">
                    @foreach ($this->roleOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">
                    Détermine les modules consultables et modifiables par ce choriste.
                </p>
            </div>

            <div>
                <x-input-label for="username" value="Identifiant de connexion" />
                <x-text-input id="username" type="text" wire:model="username" class="mt-1 block w-full"
                    autocapitalize="none" spellcheck="false" required />
                <x-input-error :messages="$errors->get('username')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="email" value="Adresse e-mail" />
                <x-text-input id="email" type="email" wire:model="email" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="password" value="Mot de passe" />
                <x-text-input id="password" type="password" wire:model="password" class="mt-1 block w-full"
                    autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-1" />
                <p class="mt-1 text-xs text-slate-500">Laissez vide pour générer un mot de passe aléatoire.</p>
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-4">
            @if ($this->existingAccount)
                <button type="button" wire:click="revoke"
                    wire:confirm="Retirer l'accès de {{ $member->full_name }} ?"
                    class="btn btn-ghost text-red-600 hover:bg-red-50">
                    <x-icon name="trash" class="h-4 w-4" />
                    Retirer l'accès
                </button>
            @else
                <span></span>
            @endif

            <div class="flex items-center gap-3">
                <button type="button" wire:click="$dispatch('close-access')"
                    class="btn btn-secondary">
                    Annuler
                </button>
                <button type="submit"
                    class="btn btn-primary">
                    <span wire:loading.remove wire:target="save">Enregistrer</span>
                    <span wire:loading wire:target="save">Enregistrement…</span>
                </button>
            </div>
        </div>
    </form>
</div>
