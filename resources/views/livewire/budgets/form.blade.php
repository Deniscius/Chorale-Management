<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="budgets-form-year" value="Exercice" />
                <x-text-input id="budgets-form-year" type="number" min="2000" max="2100" name="year" aria-describedby="budgets-form-year-error" aria-invalid="{{ $errors->has('year') ? 'true' : 'false' }}" wire:model="year" class="mt-1 block w-full" required />
                <x-input-error id="budgets-form-year-error" :messages="$errors->get('year')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="budgets-form-category" value="Poste budgétaire" />
                <select id="budgets-form-category" aria-describedby="budgets-form-category-error" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" wire:model="category"
                    class="form-select mt-1">
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="budgets-form-category-error" :messages="$errors->get('category')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="budgets-form-choir_id" value="Chorale" />
                <select id="budgets-form-choir_id" aria-describedby="budgets-form-choir_id-error" aria-invalid="{{ $errors->has('choir_id') ? 'true' : 'false' }}" wire:model="choir_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->choirs as $choir)
                        <option value="{{ $choir->id }}">{{ $choir->name }}</option>
                    @endforeach
                </select>
                <x-input-error id="budgets-form-choir_id-error" :messages="$errors->get('choir_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="budgets-form-allocated_amount" value="Montant alloué (FCFA)" />
                <x-text-input id="budgets-form-allocated_amount" type="number" step="0.01" min="0" name="allocated_amount" aria-describedby="budgets-form-allocated_amount-error" aria-invalid="{{ $errors->has('allocated_amount') ? 'true' : 'false' }}" wire:model="allocated_amount" class="mt-1 block w-full" />
                <x-input-error id="budgets-form-allocated_amount-error" :messages="$errors->get('allocated_amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="budgets-form-committed_amount" value="Montant engagé (FCFA)" />
                <x-text-input id="budgets-form-committed_amount" type="number" step="0.01" min="0" name="committed_amount" aria-describedby="budgets-form-committed_amount-error" aria-invalid="{{ $errors->has('committed_amount') ? 'true' : 'false' }}" wire:model="committed_amount" class="mt-1 block w-full" />
                <x-input-error id="budgets-form-committed_amount-error" :messages="$errors->get('committed_amount')" class="mt-1" />
            </div>

            <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                Le réalisé est calculé depuis les dépenses validées.
                @if ($budget && (float) $budget->opening_realized_amount > 0)
                    Reprise antérieure conservée : <x-money :value="$budget->opening_realized_amount" />.
                @endif
            </div>
        </div>

        <div>
            <x-input-label for="budgets-form-notes" value="Notes" />
            <textarea id="budgets-form-notes" aria-describedby="budgets-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="budgets-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
        </div>

        <div class="flex items-center justify-end gap-3 border-t border-slate-200 pt-4">
            <button type="button" wire:click="$dispatch('close-form')"
                class="btn btn-secondary">
                Annuler
            </button>
            <button type="submit" wire:loading.attr="disabled" wire:target="save"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
