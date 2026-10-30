<div>
    <x-page-header title="Types de contribution" subtitle="Configurez les échéances et les fonds prévus propres à votre chorale." icon="cog">
        <x-slot name="actions">
            <button type="button" wire:click="openLabels" class="btn btn-secondary">Noms des rubriques</button>
            <button type="button" wire:click="openFunding" class="btn btn-secondary">Fonds par célébration</button>
            <button type="button" wire:click="create" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Nouveau type</button>
        </x-slot>
    </x-page-header>

    <p class="mb-5 rounded-xl bg-blue-50 p-4 text-sm text-blue-900">Une échéance récurrente est créée au jour choisi. Les montants et règlements déjà enregistrés restent inchangés quand vous modifiez une règle.</p>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <div class="border-b p-5"><h2 class="font-semibold">Règles de la chorale</h2><p class="mt-1 text-sm text-slate-500">Les types désactivés restent visibles dans l’historique.</p></div>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-500"><tr><th class="p-3">Type</th><th class="p-3">Rubrique</th><th class="p-3">Rythme et échéance</th><th class="p-3">Montant</th><th class="p-3">État</th><th class="p-3 text-right">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($this->types as $type)
                    <tr wire:key="financial-type-{{ $type->id }}" class="hover:bg-slate-50">
                        <td class="p-3 font-medium text-slate-900">{{ $type->label }}</td>
                        <td class="p-3">{{ $type->kind === 'contribution' ? $contribution_label : $donation_label }}</td>
                        <td class="p-3">
                            @if($type->kind === 'contribution' && $type->frequency_months > 0)
                                <span class="font-medium">{{ \App\Enums\ContributionFrequency::from($type->frequency_months)->label() }}</span>
                                <p class="text-xs text-slate-500">Jour {{ $type->due_day }} · début {{ $type->starts_on?->format('d/m/Y') ?? 'à l’adhésion' }}</p>
                            @else
                                <span class="text-slate-500">Saisie ponctuelle</span>
                            @endif
                        </td>
                        <td class="p-3">{{ $type->default_amount !== null ? number_format((float)$type->default_amount, 0, ',', ' ').' FCFA' : 'Libre' }}</td>
                        <td class="p-3"><span class="{{ $type->is_active ? 'text-emerald-700' : 'text-slate-500' }}">{{ $type->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                        <td class="p-3 text-right"><button type="button" wire:click="edit({{ $type->id }})" class="btn btn-ghost" aria-label="Modifier {{ $type->label }}"><x-icon name="pencil" class="h-4 w-4" /> Modifier</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table></div>
    </section>

    @if($showTypeModal)
        <x-modal-panel :title="$editingId ? 'Modifier la règle' : 'Nouvelle règle'" subtitle="Le calcul automatique respecte la date d’adhésion et le jour d’échéance." close-event="cancel" size="lg">
            <form wire:submit="save" class="space-y-4">
                <x-validation-summary />
                <div><x-input-label for="finance-kind" value="Rubrique" /><select id="finance-kind" wire:model.live="kind" class="form-select mt-1" @disabled($editingId)><option value="contribution">Cotisations</option><option value="donation">Dons ponctuels</option></select></div>
                <div><x-input-label for="finance-label" value="Nom du type" /><x-text-input id="finance-label" wire:model="label" class="mt-1 w-full" placeholder="Ex. Cotisation mensuelle" maxlength="80" required autofocus /><x-input-error :messages="$errors->get('label')" /></div>
                @if($kind === 'donation' && !$editingId)
                    <div><x-input-label for="finance-base" value="Nature comptable" /><select id="finance-base" wire:model="base_type" class="form-select mt-1"><option value="autre">Contribution financière</option><option value="don_materiel">Don en nature / matériel</option></select></div>
                @endif
                <div><x-input-label for="finance-default" value="Montant attendu (FCFA)" /><x-text-input id="finance-default" type="number" step="0.01" min="0" max="999999999.99" wire:model="default_amount" class="mt-1 w-full" /><x-input-error :messages="$errors->get('default_amount')" /></div>
                @if($kind === 'contribution')
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div><x-input-label for="finance-frequency" value="Rythme" /><select id="finance-frequency" wire:model.live="frequency_months" class="form-select mt-1">@foreach($this->frequencyOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select><x-input-error :messages="$errors->get('frequency_months')" /></div>
                        @if($frequency_months > 0)
                            <div><x-input-label for="finance-start" value="Début de la règle" /><x-text-input id="finance-start" type="date" wire:model="starts_on" class="mt-1 w-full" /><p class="mt-1 text-xs text-slate-500">Vide : date d’adhésion.</p><x-input-error :messages="$errors->get('starts_on')" /></div>
                            <div><x-input-label for="finance-day" value="Jour d’échéance" /><x-text-input id="finance-day" type="number" min="1" max="31" wire:model="due_day" class="mt-1 w-full" required /><p class="mt-1 text-xs text-slate-500">Le dernier jour est utilisé si nécessaire.</p><x-input-error :messages="$errors->get('due_day')" /></div>
                        @endif
                    </div>
                @endif
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active" class="rounded border-slate-300" />Disponible pour les nouvelles échéances</label>
                <div class="flex justify-end gap-3 border-t pt-4"><button type="button" wire:click="cancel" class="btn btn-secondary">Annuler</button><button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Enregistrer</button></div>
            </form>
        </x-modal-panel>
    @endif

    @if($showLabelsModal)
        <x-modal-panel title="Noms et exercice comptable" subtitle="Adaptez les mots utilisés dans l’interface et la période de référence de la chorale." close-event="close-labels" size="md">
            <form wire:submit="saveLabels" class="space-y-4">
                <div><x-input-label for="contribution-label" value="Rubrique des cotisations" /><x-text-input id="contribution-label" wire:model="contribution_label" class="mt-1 w-full" maxlength="60" required /><x-input-error :messages="$errors->get('contribution_label')" /></div>
                <div><x-input-label for="donation-label" value="Rubrique des dons" /><x-text-input id="donation-label" wire:model="donation_label" class="mt-1 w-full" maxlength="60" required /><x-input-error :messages="$errors->get('donation_label')" /></div>
                <div><x-input-label for="exercise-mode" value="Année d’exercice" /><select id="exercise-mode" wire:model.live="exercise_mode" class="form-select mt-1"><option value="civil">Année civile (janvier à décembre)</option><option value="pastoral">Année pastorale</option></select><x-input-error :messages="$errors->get('exercise_mode')" /></div>
                @if($exercise_mode === 'pastoral')
                    <div><x-input-label for="pastoral-start-month" value="Mois de début de l’année pastorale" /><select id="pastoral-start-month" wire:model="pastoral_start_month" class="form-select mt-1">@foreach(range(1, 12) as $month)<option value="{{ $month }}">{{ \App\Support\Months::label($month) }}</option>@endforeach</select><x-input-error :messages="$errors->get('pastoral_start_month')" /></div>
                @endif
                <div class="flex justify-end gap-3 border-t pt-4"><button type="button" wire:click="$dispatch('close-labels')" class="btn btn-secondary">Annuler</button><button class="btn btn-primary" type="submit">Enregistrer</button></div>
            </form>
        </x-modal-panel>
    @endif

    @if($showFundingModal)
        <x-modal-panel title="Fonds de soutien par célébration" subtitle="Ces montants préremplissent le budget de chaque nouvel événement." close-event="close-funding" size="lg">
            <form wire:submit="saveFunding" class="space-y-4">
                <div class="grid gap-4 sm:grid-cols-2">@foreach($this->performanceTypeOptions as $value => $label)<div><x-input-label for="fund-{{ $value }}" :value="$label.' (FCFA)'" /><x-text-input id="fund-{{ $value }}" type="number" min="0" step="0.01" wire:model="performance_budgets.{{ $value }}" class="mt-1 w-full" /><x-input-error :messages="$errors->get('performance_budgets.'.$value)" /></div>@endforeach</div>
                <div class="flex justify-end gap-3 border-t pt-4"><button type="button" wire:click="$dispatch('close-funding')" class="btn btn-secondary">Annuler</button><button class="btn btn-primary" type="submit">Enregistrer</button></div>
            </form>
        </x-modal-panel>
    @endif
</div>
