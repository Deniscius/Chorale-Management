<div>
    <x-page-header title="Comptes d’opération" icon="banknotes" subtitle="Créez vos comptes et adaptez les comptes existants au fonctionnement de la chorale.">
        <x-slot name="actions">
            <a href="{{ route('transactions.index') }}" wire:navigate class="btn btn-secondary">Opérations</a>
            @if($this->canManage())<button type="button" wire:click="create" class="btn btn-primary">Nouveau compte</button>@endif
        </x-slot>
    </x-page-header>
    <p class="mb-4 text-sm text-slate-600">Un compte désactivé reste visible dans l’historique. Il ne peut plus être choisi pour une nouvelle opération.</p>
    <x-filter-bar class="mb-4">
        <div class="grid gap-4 md:grid-cols-2">
            <x-input id="account-search" name="search" label="Rechercher un compte" wire:model.live.debounce.300ms="search" />
            <div><label for="account-state" class="form-label">État du compte</label><select id="account-state" wire:model.live="state" class="form-select"><option value="">Tous les comptes</option><option value="active">Actifs</option><option value="inactive">Désactivés</option></select></div>
        </div>
    </x-filter-bar>
    <x-data-table :headers="[['label'=>'Code'], ['label'=>'Nom'], ['label'=>'Type'], ['label'=>'Écritures'], ['label'=>'État'], ['label'=>'Actions']]" empty-title="Aucun compte" empty-message="Créez votre premier compte d’opération.">
        @foreach($this->accounts as $account)
            <tr wire:key="account-{{ $account->id }}">
                <td class="px-4 py-3">{{ $account->code }}</td><td class="px-4 py-3">{{ $account->name }}<p class="text-xs text-slate-500">{{ $account->description }}</p></td>
                <td class="px-4 py-3">{{ $account->type->label() }}</td><td class="px-4 py-3"><a href="{{ route('transactions.index', ['compte' => $account->id]) }}" wire:navigate class="underline">{{ $account->transactions_count }} écriture(s)</a></td><td class="px-4 py-3">{{ $account->is_active ? 'Actif' : 'Désactivé' }}</td>
                <td class="px-4 py-3">@if($this->canManage())<button type="button" wire:click="edit({{ $account->id }})" class="btn btn-secondary btn-sm">Modifier</button>@endif</td>
            </tr>
        @endforeach
    </x-data-table>
    @if($showForm)
        <section x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })" class="work-surface mt-5 p-5" aria-label="Formulaire de compte">
            <h2 class="mb-4 font-semibold">{{ $editingId ? 'Modifier le compte' : 'Nouveau compte' }}</h2>
            <form wire:submit="save" class="space-y-4">
                <x-validation-summary />
                <div class="grid gap-4 md:grid-cols-3">
                    <x-input id="account-code" name="code" label="Code" wire:model="code" required />
                    <x-input id="account-name" name="name" label="Nom du compte" wire:model="name" required />
                    <div><label for="account-type" class="form-label">Type</label><select id="account-type" wire:model="type" class="form-select">@foreach($types as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                </div>
                <x-input id="account-description" name="description" label="Description" wire:model="description" />
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="is_active"> Compte actif</label>
                <div class="flex gap-3"><button type="submit" wire:loading.attr="disabled" class="btn btn-primary">Enregistrer</button><button type="button" wire:click="$set('showForm', false)" class="btn btn-secondary">Annuler</button></div>
            </form>
        </section>
    @endif
</div>
