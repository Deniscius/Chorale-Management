<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="year" value="Exercice" />
                <x-text-input id="year" type="number" min="2000" max="2100" wire:model="year" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('year')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="category" value="Poste budgétaire" />
                <select id="category" wire:model="category"
                    class="form-select mt-1">
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('category')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="choir_id" value="Chorale" />
                <select id="choir_id" wire:model="choir_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->choirs as $choir)
                        <option value="{{ $choir->id }}">{{ $choir->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('choir_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="allocated_amount" value="Montant alloué (FCFA)" />
                <x-text-input id="allocated_amount" type="number" step="0.01" min="0" wire:model="allocated_amount" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('allocated_amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="committed_amount" value="Montant engagé (FCFA)" />
                <x-text-input id="committed_amount" type="number" step="0.01" min="0" wire:model="committed_amount" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('committed_amount')" class="mt-1" />
            </div>

            <div class="rounded-lg bg-slate-50 p-3 text-sm text-slate-600">
                Le réalisé est calculé depuis les dépenses validées.
                @if ($budget && (float) $budget->opening_realized_amount > 0)
                    Reprise antérieure conservée : <x-money :value="$budget->opening_realized_amount" />.
                @endif
            </div>
        </div>

        <div>
            <x-input-label for="notes" value="Notes" />
            <textarea id="notes" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error :messages="$errors->get('notes')" class="mt-1" />
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
