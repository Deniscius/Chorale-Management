<div>
    <form wire:submit="save" class="space-y-5">
        <x-validation-summary />
        <p class="text-sm text-slate-500">Un don financier prépare une recette en brouillon, à valider par le trésorier. Un don en nature ne modifie pas la trésorerie.</p>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="donations-form-donation_type" value="Type de contribution" />
                <select id="donations-form-donation_type" aria-describedby="donations-form-donation_type-error" aria-invalid="{{ $errors->has('donation_type') ? 'true' : 'false' }}" wire:model.live="donation_type"
                    class="form-select mt-1">
                    @foreach ($this->donationTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="donations-form-donation_type-error" :messages="$errors->get('donation_type')" class="mt-1" />
                @if ($this->donationTypeDescription)
                    <p class="mt-1 text-xs text-slate-500">{{ $this->donationTypeDescription }}</p>
                @endif
            </div>

            <div>
                <x-input-label for="donations-form-donor_type" value="Type de donateur" />
                <select id="donations-form-donor_type" aria-describedby="donations-form-donor_type-error" aria-invalid="{{ $errors->has('donor_type') ? 'true' : 'false' }}" wire:model.live="donor_type"
                    class="form-select mt-1">
                    @foreach ($this->donorTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error id="donations-form-donor_type-error" :messages="$errors->get('donor_type')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">

            <div>
                <x-input-label for="donations-form-donor_name" value="Nom du donateur" />
                <x-text-input id="donations-form-donor_name" type="text" name="donor_name" aria-describedby="donations-form-donor_name-error" aria-invalid="{{ $errors->has('donor_name') ? 'true' : 'false' }}" wire:model="donor_name" class="mt-1 block w-full" />
                <x-input-error id="donations-form-donor_name-error" :messages="$errors->get('donor_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="donations-form-member_id" value="Choriste concerné" />
                <x-input id="donations-form-memberSearch" name="memberSearch" label="Rechercher un choriste" wire:model.live.debounce.300ms="memberSearch" placeholder="Nom ou prénom" />
                <p class="mb-1 text-xs text-slate-500">30 premiers résultats ; affinez la recherche si nécessaire.</p>
                <select id="donations-form-member_id" aria-describedby="donations-form-member_id-error" aria-invalid="{{ $errors->has('member_id') ? 'true' : 'false' }}" wire:model.live="member_id" @disabled($donor_type === \App\Enums\DonorType::Anonyme->value)
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->members as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error id="donations-form-member_id-error" :messages="$errors->get('member_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="donations-form-amount" value="Montant (FCFA)" />
                <x-text-input id="donations-form-amount" type="number" step="0.01" min="0" name="amount" aria-describedby="donations-form-amount-error" aria-invalid="{{ $errors->has('amount') ? 'true' : 'false' }}" wire:model="amount" class="mt-1 block w-full" required />
                <x-input-error id="donations-form-amount-error" :messages="$errors->get('amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="donations-form-donated_on" value="Date du don" />
                <x-text-input id="donations-form-donated_on" type="date" name="donated_on" aria-describedby="donations-form-donated_on-error" aria-invalid="{{ $errors->has('donated_on') ? 'true' : 'false' }}" wire:model="donated_on" class="mt-1 block w-full" required />
                <x-input-error id="donations-form-donated_on-error" :messages="$errors->get('donated_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="donations-form-purpose" value="Motif / affectation" />
                <x-text-input id="donations-form-purpose" type="text" name="purpose" aria-describedby="donations-form-purpose-error" aria-invalid="{{ $errors->has('purpose') ? 'true' : 'false' }}" wire:model="purpose" class="mt-1 block w-full" />
                <x-input-error id="donations-form-purpose-error" :messages="$errors->get('purpose')" class="mt-1" />
            </div>
        </div>

        <div>
            <x-input-label for="donations-form-notes" value="Notes" />
            <textarea id="donations-form-notes" aria-describedby="donations-form-notes-error" aria-invalid="{{ $errors->has('notes') ? 'true' : 'false' }}" wire:model="notes" rows="3"
                class="form-textarea mt-1"></textarea>
            <x-input-error id="donations-form-notes-error" :messages="$errors->get('notes')" class="mt-1" />
        </div>

        <div>
            <x-input-label for="donations-form-payment_method" value="Mode de règlement" />
            <select id="donations-form-payment_method" aria-describedby="donations-form-payment_method-error" aria-invalid="{{ $errors->has('payment_method') ? 'true' : 'false' }}" wire:model="payment_method" class="form-select mt-1">
                @foreach (\App\Enums\PaymentMethod::options() as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error id="donations-form-payment_method-error" :messages="$errors->get('payment_method')" class="mt-1" />
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
