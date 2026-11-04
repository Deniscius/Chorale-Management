<div class="space-y-5">
    <x-page-header title="Espace des maîtres de chœur" icon="music"
        subtitle="Responsables de la préparation musicale et de la conduite des répétitions.">
        <x-slot name="actions">
            <a href="{{ route('rehearsals.index') }}" wire:navigate class="btn btn-secondary">Répétitions</a>
            <a href="{{ route('works.index') }}" wire:navigate class="btn btn-secondary">Œuvres et partitions</a>
            @can('manage-users')
                <a href="{{ route('commissions.index') }}" wire:navigate class="btn btn-primary">Gérer les responsables</a>
            @endcan
        </x-slot>
    </x-page-header>
    <p class="text-sm text-slate-600">Chaque commission liturgique désigne son maître de chœur responsable et, si nécessaire, d'autres maîtres de chœur. La direction désigne le maître de chœur responsable de chaque commission liturgique. Celui-ci peut ensuite choisir et retirer ses maîtres associés, sans changer leurs rôles généraux.</p>
    <div class="grid gap-4 md:grid-cols-2">
        @forelse ($commissions as $commission)
            <section wire:key="choir-masters-{{ $commission->id }}" class="rounded-xl border border-slate-200 bg-white p-5">
                <h2 class="font-semibold text-slate-900">{{ $commission->name }}</h2>
                <div class="mt-3 rounded-lg bg-sky-50 p-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-700">Maître de chœur responsable</p>
                    <p class="mt-1 text-sm font-semibold text-slate-900">{{ $commission->leader?->name ?? 'À désigner' }}</p>
                </div>
                @if(auth()->user()->isAdmin() || (int) $commission->leader_id === (int) auth()->id())
                    <button type="button" wire:click="editAssociates({{ $commission->id }})" class="btn btn-secondary mt-3">Choisir les maîtres associés</button>
                @endif
                <h3 class="mt-4 text-sm font-semibold text-slate-800">Maîtres associés</h3>
                <ul class="mt-2 space-y-2">
                    @forelse($commission->choirMasters as $master)
                        <li wire:key="master-{{ $commission->id }}-{{ $master->id }}" class="rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-700">{{ $master->name }}</li>
                    @empty
                        <li class="text-sm text-slate-500">Aucun maître de chœur associé pour le moment.</li>
                    @endforelse
                </ul>
            </section>
        @empty
            <div class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-sm text-slate-600">Aucune commission liturgique configurée. L'administrateur peut rattacher une commission au module liturgique.</div>
        @endforelse
    </div>
    @if($editingCommissionId !== null)
        <section class="rounded-xl border border-sky-200 bg-white p-5" aria-label="Choisir les maîtres associés">
            <form wire:submit="saveAssociates" class="space-y-4">
                <h2 class="font-semibold text-slate-900">Choisir les maîtres associés</h2>
                <p class="text-sm text-slate-600">Cochez les personnes chargées d'assister le maître de chœur. Les choix précédents peuvent être retirés. Aucun rôle général n'est modifié.</p>
                <div><label for="choir-master-search" class="form-label">Rechercher un compte</label><input id="choir-master-search" type="search" wire:model.live.debounce.300ms="accountSearch" class="form-input mt-1 w-full" placeholder="Nom du choriste…"><p class="mt-1 text-xs text-slate-500">60 résultats au maximum. Les personnes déjà sélectionnées restent affichées.</p></div>
                <fieldset class="max-h-72 space-y-1 overflow-y-auto rounded-lg border border-slate-200 p-3">
                    <legend class="sr-only">Comptes des maîtres associés</legend>
                    @foreach($availableAccounts as $account)
                        @if(!$commissions->contains(fn ($commission) => $commission->id === $editingCommissionId && (int) $commission->leader_id === (int) $account->id))
                            <label wire:key="choir-master-associate-{{ $account->id }}" class="flex cursor-pointer items-center gap-3 rounded-lg p-2 hover:bg-slate-50">
                                <input type="checkbox" value="{{ $account->id }}" wire:model="associateIds" class="rounded border-slate-300">
                                <span class="text-sm text-slate-800">{{ $account->name }}</span>
                            </label>
                        @endif
                    @endforeach
                </fieldset>
                <x-input-error :messages="$errors->get('associateIds')" />
                <x-input-error :messages="$errors->get('associateIds.*')" />
                <div class="flex justify-end gap-3">
                    <button type="button" wire:click="cancelAssociates" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Enregistrer les associés</button>
                </div>
            </form>
        </section>
    @endif
</div>
