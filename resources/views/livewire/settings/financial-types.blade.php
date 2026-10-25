<div>
    <x-page-header title="Types de contribution" subtitle="Adaptez les rubriques, les types et les montants proposés à votre chorale." icon="cog" />
    <p class="mb-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Ces paramètres concernent les contributions des membres à votre chorale. L’abonnement à l’application reste distinct. Les modifications de montants proposés ne changent pas les sommes déjà dues ou payées.</p>
    <div class="grid items-start gap-6 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-900">Noms des rubriques</h2>
            <form wire:submit="saveLabels" class="mt-4 space-y-4">
                <div><x-input-label for="contribution-label" value="Rubrique des cotisations" /><x-text-input id="contribution-label" wire:model="contribution_label" class="mt-1 w-full" maxlength="60" required /><x-input-error :messages="$errors->get('contribution_label')" /></div>
                <div><x-input-label for="donation-label" value="Rubrique des dons et autres contributions" /><x-text-input id="donation-label" wire:model="donation_label" class="mt-1 w-full" maxlength="60" required /><x-input-error :messages="$errors->get('donation_label')" /></div>
                <button class="btn btn-secondary" type="submit" wire:loading.attr="disabled">Enregistrer les noms</button>
            </form>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-5">
            <h2 class="font-semibold text-slate-900">{{ $editingId ? 'Modifier le type' : 'Créer un type' }}</h2>
            <form wire:submit="save" class="mt-4 space-y-4">
                <x-validation-summary />
                <div><x-input-label for="finance-kind" value="Rubrique" /><select id="finance-kind" wire:model.live="kind" class="form-select mt-1" @disabled($editingId)><option value="contribution">Cotisations par mois et exercice</option><option value="donation">Dons et contributions ponctuelles</option></select></div>
                <div><x-input-label for="finance-label" value="Nom du type" /><x-text-input id="finance-label" wire:model="label" class="mt-1 w-full" placeholder="Ex. Cotisation mensuelle, soutien transport…" maxlength="80" required /><x-input-error :messages="$errors->get('label')" /></div>
                @if($kind === 'donation' && !$editingId)
                    <div><x-input-label for="finance-base" value="Nature comptable" /><select id="finance-base" wire:model="base_type" class="form-select mt-1"><option value="autre">Contribution financière</option><option value="don_materiel">Don en nature / matériel</option></select><p class="mt-1 text-xs text-slate-500">Cette nature reste fixe après création pour préserver les règles comptables.</p></div>
                @endif
                <div><x-input-label for="finance-default" value="Montant proposé (FCFA)" /><x-text-input id="finance-default" type="number" step="0.01" min="0" max="999999999.99" wire:model="default_amount" class="mt-1 w-full" /><p class="mt-1 text-xs text-slate-500">Facultatif. Préremplit les nouvelles saisies ; le montant reste ajustable.</p><x-input-error :messages="$errors->get('default_amount')" /></div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300" />Disponible pour les nouvelles saisies</label>
                <div class="flex flex-wrap gap-3"><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">{{ $editingId ? 'Enregistrer le type' : 'Créer le type' }}</button>@if($editingId)<button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button>@endif</div>
            </form>
        </section>
    </div>
    <section class="mt-6 overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b p-5"><h2 class="font-semibold">Types disponibles dans votre chorale</h2><p class="mt-1 text-sm text-slate-500">Désactivez un type pour le retirer des nouvelles saisies. Il reste lisible dans l’historique.</p></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3">Type</th><th class="p-3">Rubrique</th><th class="p-3">Montant proposé</th><th class="p-3">État</th><th class="p-3">Action</th></tr></thead><tbody class="divide-y divide-slate-100">
            @foreach($this->types as $type)<tr wire:key="financial-type-{{ $type->id }}"><td class="p-3 font-medium">{{ $type->label }}</td><td class="p-3">{{ $type->kind === 'contribution' ? $contribution_label : $donation_label }}</td><td class="p-3">{{ $type->default_amount !== null ? number_format((float)$type->default_amount, 2, ',', ' ').' FCFA' : 'Libre' }}</td><td class="p-3">{{ $type->is_active ? 'Actif' : 'Désactivé' }}</td><td class="p-3"><button type="button" wire:click="edit({{ $type->id }})" class="btn btn-ghost" aria-label="Modifier {{ $type->label }}">Modifier</button></td></tr>@endforeach
        </tbody></table></div>
    </section>
</div>
