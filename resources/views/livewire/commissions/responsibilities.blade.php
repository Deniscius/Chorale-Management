<form wire:submit="save" class="space-y-5">
    <x-validation-summary />
    <div class="rounded-xl bg-sky-50 p-4 text-sm text-sky-950"><strong>{{ $this->entry->name }}</strong><p class="mt-1">Les membres consultent les fonctionnalités associées. Le responsable peut les gérer. Une délégation ne change ni l’identité ni le rôle général du compte.</p></div>
    <div><x-input-label for="commission-account-search" value="Rechercher un compte"/><x-text-input id="commission-account-search" wire:model.live.debounce.250ms="search" data-modal-transient class="mt-1 w-full" placeholder="Nom de la personne…"/><p class="mt-1 text-xs text-slate-500">Seuls les comptes déjà activés sont proposés. Invitez d’abord un choriste sans compte.</p></div>
    <div><x-input-label for="commission-leader" value="Responsable de la commission"/><select id="commission-leader" wire:model="leader_id" class="form-select mt-1"><option value="">Non délégué</option>@foreach($this->accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('leader_id')"/><p class="mt-1 text-xs text-slate-500">Si une fiche choriste est liée au compte, elle rejoint automatiquement l’équipe. Le responsable précédent conserve seulement ses autres droits et ses affectations.</p></div>
    <div><x-input-label for="commission-module" value="Fonctionnalités associées"/><select id="commission-module" wire:model.live="module_key" class="form-select mt-1" @disabled($this->entry->archive_commission)><option value="">Espace de mission uniquement</option>@foreach(\App\Models\ChoirCommission::moduleLabels() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select><x-input-error :messages="$errors->get('module_key')"/><p class="mt-1 text-xs text-slate-500">L’accès porte sur ce module de la chorale. Les droits d’une mission temporaire cessent après son échéance ou son archivage.</p></div>
    @if($module_key === 'instruments')
        <fieldset class="rounded-xl border border-slate-200 p-4">
            <legend class="px-1 text-sm font-semibold text-slate-900">Associés du responsable des instruments</legend>
            <p class="mb-3 text-sm text-slate-600">Les associés désignés peuvent créer, modifier et retirer les articles, gérer les mouvements et imprimer les étiquettes. Les autres membres restent en consultation.</p>
            <div class="max-h-52 space-y-2 overflow-y-auto">
                @foreach($this->accounts as $account)
                    <label wire:key="instrument-associate-{{ $account->id }}" class="flex items-center gap-3 rounded-lg p-2 hover:bg-slate-50"><input type="checkbox" wire:model="instrument_associate_ids" value="{{ $account->id }}" class="rounded border-slate-300 text-amber-600"><span class="text-sm">{{ $account->name }}</span></label>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('instrument_associate_ids')"/>
            <p class="mt-3 text-xs text-slate-500">20 associés maximum. Le responsable ne doit pas être sélectionné comme associé. Retirer une personne de cette liste révoque sa délégation, sous réserve de ses autres rôles.</p>
        </fieldset>
    @endif
    @if($privateArchive)
        <div><x-input-label for="commission-associate" value="Associé désigné pour les archives privées"/><select id="commission-associate" wire:model="associate_id" class="form-select mt-1"><option value="">Aucun associé</option>@foreach($this->accounts as $account)<option value="{{ $account->id }}">{{ $account->name }}</option>@endforeach</select><x-input-error :messages="$errors->get('associate_id')"/></div>
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950">Les pièces privées restent réservées au responsable et à son associé désigné. Les membres ordinaires voient les documents explicitement partagés.</p>
    @endif
    <div class="flex flex-wrap justify-end gap-3 border-t pt-4"><button type="button" x-on:click="requestClose()" class="btn btn-secondary">Annuler</button><button type="submit" wire:loading.attr="disabled" class="btn btn-primary"><span wire:loading.remove wire:target="save">Enregistrer la délégation</span><span wire:loading wire:target="save">Enregistrement…</span></button></div>
</form>
