<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="transactions-form-type" value="Nature" />
                <select id="transactions-form-type" aria-describedby="transactions-form-type-error" aria-invalid="{{ $errors->has('type') ? 'true' : 'false' }}" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="transactions-form-type-error" :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transactions-form-transaction_date" value="Date" />
                <x-text-input id="transactions-form-transaction_date" type="date" name="transaction_date" aria-describedby="transactions-form-transaction_date-error" aria-invalid="{{ $errors->has('transaction_date') ? 'true' : 'false' }}" wire:model="transaction_date" class="mt-1 block w-full" required />
                <x-input-error id="transactions-form-transaction_date-error" :messages="$errors->get('transaction_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transactions-form-amount" value="Montant (FCFA)" />
                <x-text-input id="transactions-form-amount" type="number" step="0.01" min="0" name="amount" aria-describedby="transactions-form-amount-error" aria-invalid="{{ $errors->has('amount') ? 'true' : 'false' }}" wire:model="amount" class="mt-1 block w-full" required />
                <x-input-error id="transactions-form-amount-error" :messages="$errors->get('amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transactions-form-status" value="Statut" />
                <select id="transactions-form-status" aria-describedby="transactions-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="transactions-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="transactions-form-description" value="Libellé" />
            <x-text-input id="transactions-form-description" type="text" name="description" aria-describedby="transactions-form-description-error" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" wire:model="description" class="mt-1 block w-full" required />
            <x-input-error id="transactions-form-description-error" :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="transactions-form-account_id" value="Compte" />
                <select id="transactions-form-account_id" aria-describedby="transactions-form-account_id-error" aria-invalid="{{ $errors->has('account_id') ? 'true' : 'false' }}" wire:model="account_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
                <x-input-error id="transactions-form-account_id-error" :messages="$errors->get('account_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transactions-form-budget_id" value="Ligne budgétaire" />
                <select id="transactions-form-budget_id" aria-describedby="transactions-form-budget_id-error" aria-invalid="{{ $errors->has('budget_id') ? 'true' : 'false' }}" wire:model="budget_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->budgets as $budget)
                        <option value="{{ $budget->id }}">{{ $budget->year }} — {{ $budget->category }}</option>
                    @endforeach
                </select>
                <x-input-error id="transactions-form-budget_id-error" :messages="$errors->get('budget_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transactions-form-category" value="Poste" />
                <input id="transactions-form-category" type="text" list="categories" aria-describedby="transactions-form-category-error" aria-invalid="{{ $errors->has('category') ? 'true' : 'false' }}" wire:model="category"
                    class="form-select mt-1">
                <datalist id="transactions-form-categories">
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}"></option>
                    @endforeach
                </datalist>
                <x-input-error id="transactions-form-category-error" :messages="$errors->get('category')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transactions-form-payment_method" value="Mode de paiement" />
                <select id="transactions-form-payment_method" aria-describedby="transactions-form-payment_method-error" aria-invalid="{{ $errors->has('payment_method') ? 'true' : 'false' }}" wire:model="payment_method"
                    class="form-select mt-1">
                    @foreach ($this->paymentMethodOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="transactions-form-payment_method-error" :messages="$errors->get('payment_method')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="transactions-form-member_id" value="Choriste / tiers" />
                <x-input id="transactions-form-memberSearch" name="memberSearch" label="Rechercher un choriste" wire:model.live.debounce.300ms="memberSearch" placeholder="Nom ou prénom" />
                <p class="mb-1 text-xs text-slate-500">30 premiers résultats ; affinez la recherche si nécessaire.</p>
                <select id="transactions-form-member_id" aria-describedby="transactions-form-member_id-error" aria-invalid="{{ $errors->has('member_id') ? 'true' : 'false' }}" wire:model="member_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->members as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error id="transactions-form-member_id-error" :messages="$errors->get('member_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transactions-form-activity_id" value="Activité liée" />
                <select id="transactions-form-activity_id" aria-describedby="transactions-form-activity_id-error" aria-invalid="{{ $errors->has('activity_id') ? 'true' : 'false' }}" wire:model="activity_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->activities as $activity)
                        <option value="{{ $activity->id }}">{{ $activity->title }}</option>
                    @endforeach
                </select>
                <x-input-error id="transactions-form-activity_id-error" :messages="$errors->get('activity_id')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="transactions-form-notes" value="Notes" />
            <textarea id="transactions-form-notes" aria-describedby="transactions-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="transactions-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
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
