<div class="space-y-5">
    <x-page-header title="Commissions et accès" icon="users" :subtitle="'Affectations de '.$this->account->name">
        <x-slot name="actions"><a href="{{ route('users.index') }}" wire:navigate class="btn btn-secondary">Retour aux comptes</a></x-slot>
    </x-page-header>
    <div class="rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
        <p><strong>Choriste d’abord, responsabilités en complément.</strong> Une personne peut appartenir à plusieurs commissions. Un membre consulte les fonctionnalités associées ; le responsable peut les gérer.</p>
        <p class="mt-2">Rôle général actuel : <strong>{{ $this->account->role->label() }}</strong>. Les cases ci-dessous ne retirent pas les droits déjà donnés par ce rôle. Pour un choriste ordinaire, conservez le rôle général « Choriste », puis ajoutez ses affectations.</p>
        <p class="mt-2">Les droits des commissions s’appliquent seulement pendant leur période active. La consultation porte sur les données du module dans toute la chorale. La voix, le pupitre et les autres données personnelles ne sont pas modifiés ici.</p>
    </div>
    @if(! $this->account->member)
        <p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-950">La fiche de choriste de ce compte n’est pas disponible. Le titulaire doit compléter Mon profil ; si sa fiche a été supprimée, faites restaurer la fiche d’origine. Aucune identité ne sera créée ou attribuée à sa place ici.</p>
    @else
        <form wire:submit="save" class="space-y-4">
            <x-validation-summary />
            <label class="flex items-center gap-3 rounded-xl border bg-white p-4"><input type="checkbox" checked disabled class="h-5 w-5 rounded"><span>Choriste : {{ $this->account->member->full_name }}</span></label>
            @forelse($this->commissions as $entry)
                @php($private = $entry->archive_commission && \App\Enums\Commission::from($entry->archive_commission)->hasPrivateArchive())
                <fieldset wire:key="assignment-{{ $entry->id }}" class="rounded-xl border border-slate-200 bg-white p-4">
                    <legend class="px-2 font-bold text-slate-900">{{ $entry->name }}</legend>
                    <p class="mb-3 text-sm text-slate-700">{{ \App\Models\ChoirCommission::statuses()[$entry->status] }} · {{ \App\Models\ChoirCommission::moduleLabels()[$entry->module_key] ?? 'Mission sans module supplémentaire' }}</p>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="help-control inline-flex items-center gap-3"><input type="checkbox" wire:model="members" value="{{ $entry->id }}" class="h-5 w-5 rounded"><span>Membre — consultation</span></label>
                        <label class="help-control inline-flex items-center gap-3"><input type="checkbox" wire:model="leaders" value="{{ $entry->id }}" @disabled($entry->leader_id && (int)$entry->leader_id !== $userId) class="h-5 w-5 rounded"><span>Responsable — gestion</span></label>
                        @if($entry->module_key === 'instruments')
                            <label class="help-control inline-flex items-center gap-3"><input type="checkbox" wire:model="instrumentAssociates" value="{{ $entry->id }}" class="h-5 w-5 rounded"><span>Associé habilité — gestion du matériel</span></label>
                        @endif
                        @if($private)
                            <label class="help-control inline-flex items-center gap-3"><input type="checkbox" wire:model="archiveAssociates" value="{{ $entry->id }}" @disabled($entry->associate_id && (int)$entry->associate_id !== $userId) class="h-5 w-5 rounded"><span>Associé désigné — archives privées</span></label>
                        @endif
                    </div>
                    @if($entry->leader_id && (int)$entry->leader_id !== $userId)<p class="mt-3 text-sm text-slate-700">Responsable actuel : {{ $entry->leader?->name }}. Pour le remplacer, utilisez la gestion de cette commission.</p>@endif
                    @if($private && $entry->associate_id && (int)$entry->associate_id !== $userId)<p class="mt-2 text-sm text-slate-700">Associé actuel : {{ $entry->associate?->name }}. Son remplacement se fait dans la commission.</p>@endif
                </fieldset>
            @empty
                <p class="rounded-xl border bg-white p-4">Aucune commission créée pour cette chorale.</p>
            @endforelse
            <p class="text-sm text-slate-700">Responsable ou associé implique également l’appartenance à la commission. Un responsable ne doit pas être sélectionné comme son propre associé. Décocher toutes les cases retire les délégations de ces commissions, mais ne change pas le rôle général.</p>
            <p wire:dirty role="status" class="text-sm font-semibold text-amber-900">Changements non enregistrés. Utilisez le bouton ci-dessous pour les appliquer.</p>
            @if($saved)<p wire:dirty.remove role="status" class="text-sm font-semibold text-emerald-800">Affectations enregistrées. Les accès suivent les commissions actives et le rôle général.</p>@endif
            <button type="submit" wire:loading.attr="disabled" class="btn btn-primary max-w-full whitespace-normal">Enregistrer les affectations</button>
        </form>
    @endif
</div>
