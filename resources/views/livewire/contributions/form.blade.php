<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <p class="text-sm text-slate-500">Un encaissement prépare une recette en brouillon. Le trésorier la valide dans les opérations.</p>
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="contributions-form-member_id" value="Choriste" />
                <x-input id="contributions-form-memberSearch" name="memberSearch" label="Rechercher un choriste" wire:model.live.debounce.300ms="memberSearch" placeholder="Nom ou prénom" />
                <p class="mb-1 text-xs text-slate-500">30 premiers résultats ; affinez la recherche si nécessaire.</p>
                <select id="contributions-form-member_id" aria-describedby="contributions-form-member_id-error" aria-invalid="{{ $errors->has('member_id') ? 'true' : 'false' }}" wire:model="member_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->members as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error id="contributions-form-member_id-error" :messages="$errors->get('member_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contributions-form-year" value="Exercice" />
                <x-text-input id="contributions-form-year" type="number" min="2000" max="2100" name="year" aria-describedby="contributions-form-year-error" aria-invalid="{{ $errors->has('year') ? 'true' : 'false' }}" wire:model="year" class="mt-1 block w-full" required />
                <x-input-error id="contributions-form-year-error" :messages="$errors->get('year')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contributions-form-month" value="Mois" />
                <select id="contributions-form-month" aria-describedby="contributions-form-month-error" aria-invalid="{{ $errors->has('month') ? 'true' : 'false' }}" wire:model="month"
                    class="form-select mt-1">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \App\Support\Months::label($m) }}</option>
                    @endforeach
                </select>
                <x-input-error id="contributions-form-month-error" :messages="$errors->get('month')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="contributions-form-due_amount" value="Montant dû (FCFA)" />
                <x-text-input id="contributions-form-due_amount" type="number" step="0.01" min="0" name="due_amount" aria-describedby="contributions-form-due_amount-error" aria-invalid="{{ $errors->has('due_amount') ? 'true' : 'false' }}" wire:model="due_amount" class="mt-1 block w-full" required />
                <x-input-error id="contributions-form-due_amount-error" :messages="$errors->get('due_amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contributions-form-paid_amount" value="Montant payé (FCFA)" />
                <x-text-input id="contributions-form-paid_amount" type="number" step="0.01" min="0" name="paid_amount" aria-describedby="contributions-form-paid_amount-error" aria-invalid="{{ $errors->has('paid_amount') ? 'true' : 'false' }}" wire:model="paid_amount" class="mt-1 block w-full" />
                <x-input-error id="contributions-form-paid_amount-error" :messages="$errors->get('paid_amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contributions-form-paid_on" value="Date de règlement" />
                <x-text-input id="contributions-form-paid_on" type="date" name="paid_on" aria-describedby="contributions-form-paid_on-error" aria-invalid="{{ $errors->has('paid_on') ? 'true' : 'false' }}" wire:model="paid_on" class="mt-1 block w-full" />
                <x-input-error id="contributions-form-paid_on-error" :messages="$errors->get('paid_on')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="contributions-form-status" value="Statut" />
                <select id="contributions-form-status" aria-describedby="contributions-form-status-error" aria-invalid="{{ $errors->has('status') ? 'true' : 'false' }}" wire:model="status"
                    class="form-select mt-1">
                    @foreach ($this->statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="contributions-form-status-error" :messages="$errors->get('status')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="contributions-form-notes" value="Notes" />
                <x-text-input id="contributions-form-notes" type="text" name="notes" aria-describedby="contributions-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" class="mt-1 block w-full" />
                <x-input-error id="contributions-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="contributions-form-payment_method" value="Mode de règlement" />
            <select id="contributions-form-payment_method" aria-describedby="contributions-form-payment_method-error" aria-invalid="{{ $errors->has('payment_method') ? 'true' : 'false' }}" wire:model="payment_method" class="form-select mt-1">
                @foreach (\App\Enums\PaymentMethod::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error id="contributions-form-payment_method-error" :messages="$errors->get('payment_method')" class="mt-1" />
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
