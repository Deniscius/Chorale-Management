<div class="space-y-6">
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Rythmes de répétition</h1>
            <p class="text-sm text-slate-600">Définissez les jours, heures et lieux. Les prochains rendez-vous se synchronisent avec « Mon carnet » à chaque enregistrement.</p>
        </div>
        <a href="{{ route('rehearsals.index') }}" wire:navigate class="btn btn-secondary">Retour aux répétitions</a>
    </header>
    <form wire:submit="save" class="rounded-xl border bg-white p-5 space-y-4">
        <x-validation-summary />
        <h2 class="font-semibold">{{ $editingId ? 'Modifier le rythme' : 'Nouveau rythme hebdomadaire' }}</h2>
        <div class="grid gap-4 sm:grid-cols-3">
            <label class="block text-sm">Chorale
                <select wire:model="choir_id" class="form-select mt-1" required>
                    @foreach($choirs as $choir)<option value="{{ $choir->id }}">{{ $choir->name }}</option>@endforeach
                </select>
                @error('choir_id')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">Jour de la semaine
                <select wire:model="weekday" class="form-select mt-1">
                    @foreach([1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'] as $day=>$name)
                        <option value="{{ $day }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('weekday')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">Lieu habituel
                <input wire:model="venue" class="form-input mt-1" maxlength="255">
            </label>
            <label class="block text-sm">Début de séance
                <input wire:model="starts_at" type="time" class="form-input mt-1" required>
                @error('starts_at')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">Fin de séance
                <input wire:model="ends_at" type="time" class="form-input mt-1">
                @error('ends_at')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="inline-flex items-center gap-2 self-end text-sm">
                <input wire:model="is_active" type="checkbox"> Rythme actif
            </label>
            <label class="block text-sm">Applicable à partir du
                <input wire:model="effective_from" type="date" class="form-input mt-1" required>
                @error('effective_from')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm">Jusqu'au (facultatif)
                <input wire:model="effective_until" type="date" class="form-input mt-1">
                @error('effective_until')<span class="text-red-600">{{ $message }}</span>@enderror
            </label>
        </div>
        <div class="flex flex-wrap gap-3">
            <button class="btn btn-primary" type="submit">Enregistrer le rythme</button>
            @if($editingId)<button class="btn btn-secondary" type="button" wire:click="$set('editingId', null)">Annuler la modification</button>@endif
        </div>
    </form>
    <section class="rounded-xl border bg-white p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold">Rythmes configurés</h2>
            <button type="button" wire:click="generate" wire:confirm="Générer les séances des 45 prochains jours ? Les répétitions existantes seront conservées." class="btn btn-primary">Programmer les 45 prochains jours</button>
        </div>
        <p class="text-xs text-slate-600">Le rythme prend effet à la date « Applicable à partir du », uniquement pour le jour choisi. Si vous modifiez un rythme avec une date future, l’ancien reste valable jusqu’à la veille et le nouveau prend le relais sans trou dans le planning. Les séances personnalisées, celles ayant des présences, des œuvres ou des rappels conservent leurs données. Aucune répétition passée n’est supprimée.</p>
        <div class="overflow-x-auto">
            <table class="table w-full text-left text-sm">
                <thead><tr class="border-b"><th class="p-2">Chorale</th><th class="p-2">Jour</th><th class="p-2">Horaire</th><th class="p-2">Période</th><th class="p-2">État</th><th class="p-2"></th></tr></thead>
                <tbody>
                    @forelse($rhythms as $rhythm)
                        <tr wire:key="rhythm-{{ $rhythm->id }}" class="border-b">
                            <td class="p-2">{{ $rhythm->choir?->name }}</td>
                            <td class="p-2">{{ [1=>'Lundi',2=>'Mardi',3=>'Mercredi',4=>'Jeudi',5=>'Vendredi',6=>'Samedi',7=>'Dimanche'][$rhythm->weekday] }}</td>
                            <td class="p-2">{{ substr($rhythm->starts_at, 0, 5) }} – {{ $rhythm->ends_at ? substr($rhythm->ends_at,0,5) : '—' }}</td>
                            <td class="p-2">{{ $rhythm->effective_from->format('d/m/Y') }} – {{ $rhythm->effective_until?->format('d/m/Y') ?? 'Sans fin' }}</td>
                            <td class="p-2">{{ $rhythm->is_active ? 'Actif' : 'Inactif' }}</td>
                            <td class="p-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <button type="button" wire:click="edit({{ $rhythm->id }})" class="text-choir-700 font-semibold">Modifier</button>
                                    <button type="button" wire:click="repairNotebook({{ $rhythm->id }})"
                                        wire:confirm="Vérifier et aligner les séances futures de ce rythme dans les carnets ? Seules les séances automatiquement créées et jamais modifiées, sans présence, œuvre ou rappel, seront corrigées ou annulées. Toutes les autres seront conservées."
                                        class="font-semibold text-sky-700">Réparer les carnets</button>
                                    <button type="button" wire:click="remove({{ $rhythm->id }})"
                                        wire:confirm="{{ $rhythm->rehearsals_count ? 'Ce rythme a déjà généré des répétitions. Il sera désactivé, mais les séances, présences et notifications seront conservées. Continuer ?' : 'Ce rythme n’a jamais servi. Le supprimer définitivement ?' }}"
                                        class="font-semibold text-red-700">{{ $rhythm->rehearsals_count ? 'Arrêter le rythme' : 'Supprimer' }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-4 text-slate-500">Aucun rythme configuré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
