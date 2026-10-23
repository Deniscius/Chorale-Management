<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <p class="text-sm text-slate-600">Seuls le responsable et l’associé désignés accèdent aux documents privés. Le partage est réservé aux utilisateurs connectés et peut être retiré.</p>
        <div>
            <x-input-label for="documents-team-settings-archive-leader" value="Responsable" />
            <select id="documents-team-settings-archive-leader" aria-describedby="documents-team-settings-leader_id-error" aria-invalid="{{ $errors->has('leader_id') ? 'true' : 'false' }}" wire:model="leader_id" class="form-select" @disabled(!auth()->user()->isAdmin())>
                <option value="">Choisir le responsable</option>
                @foreach ($this->leaders as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
            </select>
            <x-input-error id="documents-team-settings-leader_id-error" :messages="$errors->get('leader_id')" />
            @if (auth()->user()->isAdmin())<p class="text-xs text-slate-500">Choisissez un compte du rôle de la commission. Si la liste est vide, attribuez d’abord ce rôle dans Utilisateurs.</p>@endif
        </div>
        <x-input id="documents-team-settings-search" name="search" data-modal-transient label="Rechercher un associé" wire:model.live.debounce.300ms="search" placeholder="Nom du compte" />
        <div>
            <x-input-label for="documents-team-settings-archive-associate" value="Associé désigné" />
            <select id="documents-team-settings-archive-associate" aria-describedby="documents-team-settings-associate_id-error" aria-invalid="{{ $errors->has('associate_id') ? 'true' : 'false' }}" wire:model="associate_id" class="form-select">
                <option value="">Aucun associé</option>
                @foreach ($this->associates as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
            </select>
            <x-input-error id="documents-team-settings-associate_id-error" :messages="$errors->get('associate_id')" />
        </div>
        <p class="text-xs text-slate-500">Le remplacement d’un associé retire son accès aux fichiers privés dès sa prochaine consultation. Les documents déjà téléchargés restent sur son appareil.</p>
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">Enregistrer les accès</button>
    </form>
</div>
