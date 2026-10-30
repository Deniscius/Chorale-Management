<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="text-2xl font-bold text-slate-900">Commissions</h1><p class="mt-1 text-sm text-slate-600">Des équipes, une mission et des responsabilités claires.</p></div>
        @can('manage-commissions')<button type="button" wire:click="create" class="btn btn-primary">Créer une commission</button>@endcan
    </div>
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        @foreach(\App\Models\ChoirCommission::statuses() as $key => $label)
            <button type="button" wire:click="filterStatus('{{ $key }}')" aria-pressed="{{ $status === $key ? 'true' : 'false' }}" class="rounded-2xl border p-4 text-left transition focus-visible:ring-2 focus-visible:ring-sky-500 {{ $status === $key ? 'border-sky-500 bg-sky-50' : 'border-slate-200 bg-white hover:border-sky-300' }}"><span class="block text-sm text-slate-600">{{ $label }}</span><span class="mt-1 block text-2xl font-bold text-slate-900">{{ $counts[$key] }}</span></button>
        @endforeach
    </div>
    <div class="rounded-2xl border border-slate-200 bg-white p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div><label for="commission-search" class="text-sm font-medium">Rechercher</label><input id="commission-search" type="search" wire:model.live.debounce.300ms="search" maxlength="120" class="form-input mt-1 w-full" placeholder="Nom ou mission…"></div>
            <div><label for="commission-status" class="text-sm font-medium">Statut</label><select id="commission-status" wire:model.live="status" class="form-select mt-1"><option value="">Tous les statuts</option>@foreach(\App\Models\ChoirCommission::statuses() as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></div>
            <div><label for="commission-kind" class="text-sm font-medium">Type</label><select id="commission-kind" wire:model.live="kindFilter" class="form-select mt-1"><option value="">Tous les types</option><option value="permanent">Permanentes</option><option value="ad_hoc">Ad hoc · temporaires</option></select></div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model.live="mine" class="rounded border-slate-300">Mes commissions</label>
        </div>
    </div>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60" wire:target="search,status,kindFilter,mine,filterStatus,gotoPage">
        @forelse($commissions as $commission)
            <article wire:key="commission-{{ $commission->id }}" class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center gap-2 text-xs"><span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium">{{ $commission->kind === 'ad_hoc' ? 'Ad hoc' : 'Permanente' }}</span><span class="rounded-full bg-sky-50 px-2.5 py-1 text-sky-800">{{ \App\Models\ChoirCommission::statuses()[$commission->status] }}</span></div>
                <h2 class="mt-3 text-lg font-semibold"><button type="button" wire:click="view({{ $commission->id }})" class="text-left hover:text-sky-700 focus-visible:underline">{{ $commission->name }}</button></h2>
                <p class="mt-2 line-clamp-3 break-words text-sm text-slate-600">{{ $commission->mission }}</p>
                <p class="mt-4 text-sm text-slate-500">{{ $commission->starts_on->format('d/m/Y') }} → {{ $commission->ends_on?->format('d/m/Y') ?? 'Sans échéance' }}</p>
                <p class="mt-1 text-sm font-medium">{{ $commission->members_count }} choriste(s)</p>
                @if($commission->activities_count)<p class="mt-1 text-sm text-slate-600">{{ $commission->activities_count }} activité(s) organisée(s)</p>@endif
                <p class="mt-2 text-sm text-slate-600">Responsable : <span class="font-medium">{{ $commission->leader?->name ?? 'À désigner' }}</span></p>
                @if($commission->module_key)<p class="mt-1 text-xs text-slate-500">{{ \App\Models\ChoirCommission::moduleLabels()[$commission->module_key] ?? '' }}</p>@endif
                <div class="mt-auto flex flex-wrap gap-2 border-t border-slate-100 pt-4 mt-4">
                    <button type="button" wire:click="view({{ $commission->id }})" class="btn btn-secondary">Ouvrir la commission</button>
                    @if(auth()->user()->isAdmin())<button type="button" wire:click="delegate({{ $commission->id }})" class="btn btn-secondary">Déléguer le responsable</button>@endif
                    @can('manage-commissions')<button type="button" wire:click="edit({{ $commission->id }})" class="btn btn-secondary">Modifier</button><x-confirm-action :call="'toggleArchive('.$commission->id.')'" :title="$commission->archived_at ? 'Réouvrir la commission' : 'Archiver la commission'" :message="$commission->archived_at ? 'Son statut dépendra de ses dates.' : 'Sa mission et ses membres seront conservés.'" :label="$commission->archived_at ? 'Réouvrir' : 'Archiver'" trigger-class="text-sm font-medium text-slate-600 underline" />@endcan
                </div>
            </article>
        @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center md:col-span-2 xl:col-span-3"><h2 class="font-semibold">Aucune commission à afficher</h2><p class="mt-2 text-sm text-slate-500">Essayez un autre filtre. La directrice peut créer les équipes et définir leur mission.</p><button type="button" wire:click="filterStatus('')" class="mt-4 text-sm text-sky-700 underline">Afficher tous les statuts</button></div>
        @endforelse
    </div>
    {{ $commissions->links() }}
    <p class="text-xs text-slate-500">Les commissions organisent les équipes. L’accès aux archives privées reste géré séparément par leurs responsables.</p>

    @if($showForm)
        <x-modal-panel :title="$editingId ? 'Modifier la commission' : 'Nouvelle commission'" subtitle="Définissez la mission, la durée et les choristes de l’équipe." size="xl">
            <form wire:submit="save" class="space-y-5">
                <x-validation-summary />
                <div><x-input-label for="commission-name" value="Nom de la commission"/><x-text-input id="commission-name" wire:model="name" class="mt-1 w-full" maxlength="120" required autofocus/><x-input-error :messages="$errors->get('name')"/></div>
                <div><x-input-label for="commission-mission" value="Mission et résultat attendu"/><textarea id="commission-mission" wire:model="mission" rows="3" maxlength="3000" required class="form-textarea mt-1" placeholder="Ex. Préparer le concert de Noël et coordonner les répétitions."></textarea><x-input-error :messages="$errors->get('mission')"/></div>
                <div><x-input-label for="commission-form-kind" value="Type de commission"/><select id="commission-form-kind" wire:model.live="kind" class="form-select mt-1"><option value="permanent">Permanente · mission continue</option><option value="ad_hoc">Ad hoc · mission temporaire</option></select></div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><x-input-label for="commission-start" value="Début de mission"/><x-text-input id="commission-start" type="date" wire:model="starts_on" class="mt-1 w-full" required/><x-input-error :messages="$errors->get('starts_on')"/></div>
                    <div><x-input-label for="commission-end" :value="$kind === 'ad_hoc' ? 'Fin de mission (obligatoire)' : 'Fin de mission (facultative)'"/><x-text-input id="commission-end" type="date" wire:model="ends_on" class="mt-1 w-full" :required="$kind === 'ad_hoc'"/><x-input-error :messages="$errors->get('ends_on')"/></div>
                </div>
                <p class="rounded-lg bg-sky-50 p-3 text-sm text-sky-900">La mission reste en cours jusqu’à la fin du dernier jour. Ensuite, elle apparaît parmi les commissions terminées ; l’historique est conservé.</p>
                <fieldset class="rounded-xl border border-slate-200 p-4"><legend class="px-1 font-semibold">Équipe · {{ count($member_ids) }} sélectionné(s)</legend>
                    <label for="commission-member-search" class="text-sm">Trouver un choriste</label><input id="commission-member-search" type="search" wire:model.live.debounce.200ms="memberSearch" class="form-input mt-1 w-full" placeholder="Prénom ou nom…">
                    <div class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                        @forelse($members->filter(fn ($member) => $memberSearch === '' || str_contains(mb_strtolower($member->full_name), mb_strtolower($memberSearch))) as $member)
                            <label wire:key="commission-member-{{ $member->id }}" class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-slate-50"><input type="checkbox" wire:model.live="member_ids" value="{{ $member->id }}" class="rounded border-slate-300"><span class="text-sm">{{ $member->full_name }} <span class="text-xs text-slate-500">{{ $member->status->label() }}</span></span></label>
                        @empty<p class="py-3 text-sm text-slate-500">Aucun choriste trouvé.</p>@endforelse
                    </div><x-input-error :messages="$errors->get('member_ids')"/>
                </fieldset>
                <div class="sticky bottom-0 flex flex-wrap justify-end gap-3 border-t bg-white pt-4"><button type="button" x-on:click="requestClose()" class="btn btn-secondary">Annuler</button><button type="submit" wire:loading.attr="disabled" class="btn btn-primary"><span wire:loading.remove wire:target="save">Enregistrer la commission</span><span wire:loading wire:target="save">Enregistrement…</span></button></div>
            </form>
        </x-modal-panel>
    @elseif($responsibilityId)
        <x-modal-panel title="Responsable et accès" subtitle="Délégation administrative, sans modification du rôle du compte." close-event="close-responsibilities" size="lg">
            <livewire:commissions.responsibilities :commission-id="$responsibilityId" :key="'responsibility-'.$responsibilityId" />
        </x-modal-panel>
    @elseif($detail)
        <x-modal-panel :title="$detail->name" subtitle="Mission et composition de l’équipe" size="lg">
            <div class="space-y-5">
                @if($detail->module_key && $detail->status === 'active' && auth()->user()->canAccessModule($detail->module_key))
                    <section class="rounded-xl bg-sky-50 p-4"><h3 class="font-semibold text-sky-950">Fonctionnalités de la commission</h3><p class="mt-1 text-sm text-sky-900">{{ auth()->user()->canManageModule($detail->module_key) ? 'Vous pouvez consulter et gérer ce module.' : 'Vous disposez d’un accès en consultation.' }}</p><div class="mt-3 flex flex-wrap gap-2">@foreach($detail->featureLinks() as $route => $label)<a href="{{ route($route) }}" class="btn btn-secondary">{{ $label }}</a>@endforeach</div></section>
                @elseif($detail->module_key)<p class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">L’accès aux fonctionnalités dépend de votre affectation et des dates de mission.</p>@endif
                <p class="whitespace-pre-line break-words text-slate-700">{{ $detail->mission }}</p><p class="text-sm text-slate-500">Du {{ $detail->starts_on->format('d/m/Y') }} {{ $detail->ends_on ? 'au '.$detail->ends_on->format('d/m/Y') : '· Sans échéance' }} · {{ \App\Models\ChoirCommission::statuses()[$detail->status] }}</p>
                @if($detail->activities->isNotEmpty())<section><h3 class="font-semibold">Activités organisées</h3><ul class="mt-2 space-y-2">@foreach($detail->activities as $activity)<li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3 text-sm"><span class="font-medium">{{ $activity->title }}</span><time class="text-slate-500">{{ $activity->starts_at->format('d/m/Y') }}</time></li>@endforeach</ul></section>@endif
                <h3 class="font-semibold">Choristes affectés ({{ $detail->members->count() }})</h3><ul class="grid gap-2 sm:grid-cols-2">@forelse($detail->members as $member)<li class="rounded-lg bg-slate-50 p-3 text-sm">{{ $member->full_name }}</li>@empty<li class="text-sm text-slate-500">L’équipe sera constituée par la directrice.</li>@endforelse</ul></div>
        </x-modal-panel>
    @endif
</div>
