<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">
            <p class="font-medium text-slate-900">{{ $member->full_name }}</p>
            @if ($this->existingAccount)
                <p class="mt-1">Compte activé. Le membre gère lui-même son identifiant et son mot de passe.</p>
            @else
                <p class="mt-1">Le membre choisira son mot de passe et confirmera son identifiant à l’activation.</p>
                <p class="mt-2">Identifiant proposé : <strong>{{ $this->suggestedUsername }}</strong>, à partir de son premier prénom.</p>
                @if ($this->invitation)
                    <p class="mt-2 text-xs">{{ $this->invitation->expires_at->isFuture() ? 'Invitation en attente' : 'Invitation expirée' }} · échéance le {{ $this->invitation->expires_at->format('d/m/Y à H:i') }}.</p>
                @endif
            @endif
        </div>
        <div>
            <x-input-label for="members-access-role" value="Rôle attribué" />
            <select id="members-access-role" wire:model="role" class="form-select mt-1" aria-describedby="members-access-role-error">
                @foreach ($this->roleOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error id="members-access-role-error" :messages="$errors->get('role')" class="mt-1" />
        </div>
        @if ($activationLink)
            <x-member-activation-link :link="$activationLink" />
        @endif
        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4">
            @if ($this->existingAccount || $this->invitation)
                <button type="button" wire:click="revoke" wire:loading.attr="disabled" wire:target="save,revoke" wire:confirm="Retirer l’accès et désactiver le lien de ce membre ?" class="btn btn-ghost text-red-600">Retirer l’accès</button>
            @endif
            <div class="ml-auto flex flex-wrap items-center gap-3">
                <button type="button" wire:click="$dispatch('close-access')" class="btn btn-secondary">Fermer</button>
                <button type="submit" wire:loading.attr="disabled" wire:target="save,revoke" class="btn btn-primary">
                    <span wire:loading.remove wire:target="save">{{ $this->existingAccount ? 'Enregistrer le rôle' : ($this->invitation ? 'Renouveler le lien' : 'Préparer l’accès') }}</span>
                    <span wire:loading wire:target="save">Enregistrement…</span>
                </button>
            </div>
        </div>
    </form>
</div>
