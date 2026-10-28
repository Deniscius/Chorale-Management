<div>
    <x-page-header title="Rapport financier des présences" icon="banknotes"
        subtitle="Absences et retards issus uniquement des fiches finalisées. Aucun élément de ce rapport ne crée automatiquement une dette ou une transaction.">
        <x-slot name="actions">
            <button type="button" onclick="window.print()" class="btn btn-secondary">
                <x-icon name="archive" class="h-4 w-4" />
                Imprimer le rapport
            </button>
        </x-slot>
    </x-page-header>

    @can('configure-attendance-finance')
        <details class="mb-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <summary class="cursor-pointer font-semibold text-slate-900">Règles financières de la chorale</summary>
            <form wire:submit="saveRules" class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <label class="flex items-center gap-3 rounded-lg border p-3"><input type="checkbox" wire:model="financialTrackingEnabled" class="rounded border-slate-300"><span class="text-sm">Activer les montants indicatifs</span></label>
                <div><label class="form-label">Absence à une répétition</label><input type="number" min="0" step="0.01" wire:model="rehearsalAbsenceAmount" class="form-input"><x-input-error :messages="$errors->get('rehearsalAbsenceAmount')" /></div>
                <div><label class="form-label">Absence à une activité</label><input type="number" min="0" step="0.01" wire:model="activityAbsenceAmount" class="form-input"><x-input-error :messages="$errors->get('activityAbsenceAmount')" /></div>
                <div><label class="form-label">Retard</label><input type="number" min="0" step="0.01" wire:model="lateAmount" class="form-input"><x-input-error :messages="$errors->get('lateAmount')" /></div>
                <div><label class="form-label">Seuil du retard (minutes)</label><input type="number" min="1" max="1440" wire:model="lateThresholdMinutes" class="form-input"><x-input-error :messages="$errors->get('lateThresholdMinutes')" /></div>
                <div><label class="form-label">Entrée en vigueur</label><input type="date" wire:model="effectiveFrom" class="form-input"><x-input-error :messages="$errors->get('effectiveFrom')" /></div>
                <div class="sm:col-span-2 xl:col-span-3"><button class="btn btn-primary">Enregistrer les règles</button></div>
            </form>
        </details>
    @endcan

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat-card label="Incidents" :value="$this->totals['count']" icon="users" color="slate" />
        <x-stat-card label="Absences" :value="$this->totals['absences']" icon="alert" color="red" />
        <x-stat-card label="Retards" :value="$this->totals['late']" icon="clock" color="amber" />
        <x-stat-card label="Montant indicatif" :value="number_format($this->totals['amount'], 0, ',', ' ').' FCFA'" icon="banknotes" color="green" />
    </div>

    <x-filter-bar class="mt-5"><div class="grid gap-3 sm:grid-cols-3">
        <div><label class="form-label" for="incident-status">Statut</label><select id="incident-status" wire:model.live="status" class="form-select"><option value="">Tous</option><option value="pending">À traiter</option><option value="waived">Dispensés</option><option value="cancelled">Annulés après correction</option></select></div>
        <div><label class="form-label" for="incident-from">Du</label><input id="incident-from" type="date" wire:model.live="from" class="form-input"></div>
        <div><label class="form-label" for="incident-to">Au</label><input id="incident-to" type="date" wire:model.live="to" class="form-input"></div>
    </div></x-filter-bar>

    <div id="resultats" class="mt-5 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Choriste</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Événement</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Incident</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase">Montant</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase">Action</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($this->incidents as $incident)
                    <tr wire:key="incident-{{ $incident->id }}"><td class="px-4 py-3 text-sm">{{ $incident->occurred_on->format('d/m/Y') }}</td><td class="px-4 py-3 text-sm font-medium">{{ $incident->member->full_name }}</td><td class="px-4 py-3 text-sm">{{ $incident->event_label }}</td><td class="px-4 py-3 text-sm">{{ $incident->incident_type === 'late' ? 'Retard de '.$incident->minutes_late.' min' : 'Absence non excusée' }}</td><td class="px-4 py-3 text-right text-sm">{{ number_format((float)$incident->amount, 0, ',', ' ') }} FCFA</td><td class="px-4 py-3 text-right">@if($incident->status === 'pending')<button wire:click="waive({{ $incident->id }})" wire:confirm="Dispenser cet incident ?" class="btn btn-soft btn-xs">Dispenser</button>@elseif($incident->status === 'waived')<button wire:click="restore({{ $incident->id }})" class="btn btn-soft btn-xs">Réintégrer</button>@else<span class="text-xs text-slate-500">Annulé</span>@endif</td></tr>
                @empty <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-600">Aucun incident pour ces filtres.</td></tr> @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $this->incidents->links() }}</div>
</div>
