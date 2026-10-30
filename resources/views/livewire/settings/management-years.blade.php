<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold text-slate-900">Gestion des années</h1>
        <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-indigo-700 underline">Retour au tableau de bord</a>
    </div>
    <p class="text-sm text-slate-600">Chaque année est propre à votre espace. Les dates pastorales et le thème sont décidés par l'administrateur.</p>

    <form wire:submit="save" class="space-y-4 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">{{ $editingId ? 'Modifier une année' : 'Créer une année' }}</h2>
        <div class="grid gap-4 md:grid-cols-2">
            <label class="block text-sm">Type
                <select wire:model="type" class="mt-1 w-full rounded-lg border-slate-300">
                    <option value="pastorale">Pastorale</option>
                    <option value="civile">Civile</option>
                </select>
            </label>
            <label class="block text-sm">Libellé
                <input wire:model="label" class="mt-1 w-full rounded-lg border-slate-300" placeholder="2026-2027" required />
                @error('label') <span class="text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">Début
                <input type="date" wire:model="starts_on" class="mt-1 w-full rounded-lg border-slate-300" required />
                @error('starts_on') <span class="text-red-600">{{ $message }}</span> @enderror
            </label>
            <label class="block text-sm">Fin
                <input type="date" wire:model="ends_on" class="mt-1 w-full rounded-lg border-slate-300" required />
                @error('ends_on') <span class="text-red-600">{{ $message }}</span> @enderror
            </label>
        </div>
        <label class="block text-sm">Thème (obligatoire pour une année pastorale)
            <input wire:model="theme" class="mt-1 w-full rounded-lg border-slate-300" maxlength="255" />
            @error('theme') <span class="text-red-600">{{ $message }}</span> @enderror
        </label>
        <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active"> Disponible à la sélection</label>
        <div class="flex gap-3">
            <button class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white">Enregistrer</button>
            @if($editingId)<button type="button" wire:click="resetForm" class="rounded-lg border px-4 py-2 text-sm">Annuler</button>@endif
        </div>
    </form>

    <div class="overflow-x-auto rounded-xl border bg-white">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-50"><tr><th class="p-3">Année</th><th class="p-3">Type</th><th class="p-3">Période</th><th class="p-3">Thème</th><th class="p-3">État</th><th class="p-3"></th></tr></thead>
            <tbody>
                @foreach($years as $year)
                    <tr wire:key="year-{{ $year->id }}" class="border-t">
                        <td class="p-3">{{ $year->label }}</td>
                        <td class="p-3">{{ ucfirst($year->type) }}</td>
                        <td class="p-3">{{ $year->starts_on->format('d/m/Y') }} – {{ $year->ends_on->format('d/m/Y') }}</td>
                        <td class="p-3">{{ $year->theme ?: '—' }}</td>
                        <td class="p-3">{{ $year->is_active ? 'Active' : 'Inactive' }}</td>
                        <td class="p-3"><button wire:click="edit({{ $year->id }})" class="font-semibold text-indigo-700">Modifier</button></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
