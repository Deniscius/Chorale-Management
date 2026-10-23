<div>
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="donation_type" value="Nature du don" />
                <select id="donation_type" wire:model.live="donation_type"
                    class="form-select mt-1">
                    @foreach ($this->donationTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('donation_type')" class="mt-1" />
                @if ($this->donationTypeDescription)
                    <p class="mt-1 text-xs text-slate-500">{{ $this->donationTypeDescription }}</p>
                @endif
            </div>

            <div>
                <x-input-label for="donor_type" value="Type de donateur" />
                <select id="donor_type" wire:model.live="donor_type"
                    class="form-select mt-1">
                    @foreach ($this->donorTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('donor_type')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">

            <div>
                <x-input-label for="donor_name" value="Nom du donateur" />
                <x-text-input id="donor_name" type="text" wire:model="donor_name" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('donor_name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="member_id" value="Choriste concerné" />
                <select id="member_id" wire:model="member_id"
                    class="form-select mt-1">
                    <option value="">—</option>
                    @foreach ($this->members as $member)
                        <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('member_id')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="amount" value="Montant (FCFA)" />
                <x-text-input id="amount" type="number" step="0.01" min="0" wire:model="amount" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="donated_on" value="Date du don" />
                <x-text-input id="donated_on" type="date" wire:model="donated_on" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('donated_on')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="purpose" value="Motif / affectation" />
                <x-text-input id="purpose" type="text" wire:model="purpose" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('purpose')" class="mt-1" />
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
