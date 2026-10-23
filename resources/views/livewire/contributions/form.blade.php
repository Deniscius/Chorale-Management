<div>
    <form wire:submit="save" class="space-y-5">
        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="member_id" value="Choriste" />
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
                <x-input-label for="year" value="Exercice" />
                <x-text-input id="year" type="number" min="2000" max="2100" wire:model="year" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('year')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="month" value="Mois" />
                <select id="month" wire:model="month"
                    class="form-select mt-1">
                    @foreach (range(1, 12) as $m)
                        <option value="{{ $m }}">{{ \App\Support\Months::label($m) }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('month')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div>
                <x-input-label for="due_amount" value="Montant dû (FCFA)" />
                <x-text-input id="due_amount" type="number" step="0.01" min="0" wire:model="due_amount" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('due_amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="paid_amount" value="Montant payé (FCFA)" />
                <x-text-input id="paid_amount" type="number" step="0.01" min="0" wire:model="paid_amount" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('paid_amount')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="paid_on" value="Date de règlement" />
                <x-text-input id="paid_on" type="date" wire:model="paid_on" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('paid_on')" class="mt-1" />
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
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

            <div>
                <x-input-label for="notes" value="Notes" />
                <x-text-input id="notes" type="text" wire:model="notes" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('notes')" class="mt-1" />
            </div>
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
