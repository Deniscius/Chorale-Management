<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Gestion des arriérés</h1>
        <a href="{{ route('contributions.index') }}" wire:navigate class="text-sm text-indigo-700 underline">Retour aux cotisations</a>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
        La remise à zéro du compteur n'annule pas les anciennes dettes : elles restent dans l'historique.
        L'exonération, elle, libère expressément le choriste des échéances impayées choisies.
        Aucun paiement existant n'est supprimé.
    </div>
    <form wire:submit="save" class="space-y-4 rounded-xl border bg-white p-5">
        <label class="block text-sm font-semibold">Opération
            <select wire:model="method" class="mt-1 w-full rounded-lg border-slate-300">
                <option value="exemption">Exonérer des échéances impayées</option>
                <option value="new_start">Nouveau départ du compteur</option>
            </select>
        </label>
        <div class="grid gap-4 sm:grid-cols-2">
            <label class="block text-sm">Choriste
                <select wire:model.live="member_id" class="mt-1 w-full rounded-lg border-slate-300" required>
                    <option value="">Choisir un choriste</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}">{{ $member->last_name }} {{ $member->first_name }}</option>
                    @endforeach
                </select>
                @error('member_id') <span class="text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">Type de cotisation
                <select wire:model.live="type_code" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="">Tous les types (nouveau départ uniquement)</option>
                    @foreach($types as $type)<option value="{{ $type->code }}">{{ $type->label }}</option>@endforeach
                </select>
                @error('type_code') <span class="text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">Date de début / nouveau départ
                <input type="date" wire:model="starts_on" class="mt-1 w-full rounded-lg border-slate-300" required>
                @error('starts_on') <span class="text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">Date de fin (exonération uniquement)
                <input type="date" wire:model="ends_on" class="mt-1 w-full rounded-lg border-slate-300">
                @error('ends_on') <span class="text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>
        @if($this->selectedMemberArrears !== null)
            <p class="rounded-lg bg-slate-50 p-3 text-sm font-semibold">Arriérés courants calculés : {{ \App\Support\Money::compact($this->selectedMemberArrears) }}</p>
        @endif
        <label class="block text-sm">Motif administratif obligatoire
            <textarea wire:model="reason" rows="3" class="mt-1 w-full rounded-lg border-slate-300" required></textarea>
            @error('reason') <span class="text-red-600">{{ $message }}</span> @enderror
            @error('period') <span class="text-red-600">{{ $message }}</span> @enderror
        </label>
        <button type="submit" wire:confirm="Confirmez-vous cette décision concernant les arriérés du choriste ?" class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">Confirmer la décision</button>
    </form>
    <div class="overflow-x-auto rounded-xl border bg-white">
        <h2 class="px-4 py-3 font-semibold">Historique des nouveaux départs</h2>
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50"><tr><th class="p-3">Choriste</th><th class="p-3">Date de départ</th><th class="p-3">Motif</th><th class="p-3">Autorisé par</th></tr></thead>
            <tbody>
                @forelse($history as $item)
                    <tr wire:key="reset-{{ $item->id }}" class="border-t">
                        <td class="p-3">{{ $item->member?->last_name }} {{ $item->member?->first_name }}</td>
                        <td class="p-3">{{ $item->starts_on->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $item->reason }}</td>
                        <td class="p-3">{{ $item->approver?->name }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-4 text-slate-500">Aucun nouveau départ enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
