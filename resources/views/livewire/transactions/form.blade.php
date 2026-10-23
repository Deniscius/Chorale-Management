<div>
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="type" value="Nature" />
                <select id="type" wire:model="type"
                    class="form-select mt-1">
                    @foreach ($this->typeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('type')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="transaction_date" value="Date" />
                <x-text-input id="transaction_date" type="date" wire:model="transaction_date" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('transaction_date')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="amount" value="Montant (FCFA)" />
                <x-text-input id="amount" type="number" step="0.01" min="0" wire:model="amount" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="status" value="Statut" />
                <select id="status" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="description" value="Libellé" />
            <x-text-input id="description" type="text" wire:model="description" class="mt-1 block w-full" required />
            <x-input-error :messages="$errors->get('description')" class="mt-1" />
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div>
                <x-input-label for="account_id" value="Compte" />
                <select id="account_id" wire:model="account_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->accounts as $account)
                        <option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('account_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="budget_id" value="Ligne budgétaire" />
                <select id="budget_id" wire:model="budget_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->budgets as $budget)
                        <option value="{{ $budget->id }}">{{ $budget->year }} — {{ $budget->category }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('budget_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="category" value="Poste" />
                <input id="category" type="text" list="categories" wire:model="category"
                    class="form-select mt-1">
                <datalist id="categories">
                    @foreach ($this->categoryOptions as $value => $label)
                        <option value="{{ $value }}"></option>
                    @endforeach
                </datalist>
                <x-input-error :messages="$errors->get('category')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="payment_method" value="Mode de paiement" />
                <select id="payment_method" wire:model="payment_method"
                    class="form-select mt-1">
                    @foreach ($this->paymentMethodOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('payment_method')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="member_id" value="Choriste / tiers" />
                <select id="member_id" wire:model="member_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->members as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('member_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="activity_id" value="Activité liée" />
                <select id="activity_id" wire:model="activity_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->activities as $activity)
                        <option value="{{ $activity->id }}">{{ $activity->title }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('activity_id')" class="mt-1" />
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
            <button type="submit"
                class="btn btn-primary">
                <span wire:loading.remove wire:target="save">Enregistrer</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
