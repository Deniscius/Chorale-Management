<div>
    <x-page-header title="Rapport financier des présences" icon="banknotes"
        subtitle="Absences et retards issus uniquement des fiches finalisées. Aucun élément de ce rapport ne crée automatiquement une dette ou une transaction.">
    </x-page-header>

    <div @class(['mb-5 rounded-xl border p-4 text-sm', 'border-emerald-200 bg-emerald-50 text-emerald-900' => $activeLetterhead, 'border-amber-200 bg-amber-50 text-amber-950' => ! $activeLetterhead])>
        @if($activeLetterhead)
            <strong>Papier à en-tête actif :</strong> {{ $activeLetterhead->original_name }}. Il sera intégré aux rapports ci-dessous.
        @else
            <strong>Aucun papier à en-tête actif.</strong> Les rapports utiliseront provisoirement le nom et les coordonnées de la chorale. Un administrateur doit vérifier puis activer le document importé dans les informations de la chorale.
        @endif
    </div>

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

    <section class="mt-5 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4">
            <h2 class="font-semibold text-slate-900">Fiches de présence validées</h2>
            <p class="mt-1 text-sm text-slate-600">Toutes les fiches transmises par le secrétariat apparaissent ici, y compris lorsqu’il n’y a ni absence ni retard.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Date</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Événement</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Présences</th><th class="px-4 py-3 text-left text-xs font-semibold uppercase">Validation</th><th class="px-4 py-3 text-right text-xs font-semibold uppercase">Rapport</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($this->finalizedSheets as $sheet)
                        <tr wire:key="validated-sheet-{{ $sheet['type'] }}-{{ $sheet['id'] }}">
                            <td class="whitespace-nowrap px-4 py-3 text-sm">{{ $sheet['date']->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $sheet['label'] }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-sm"><span class="text-emerald-700">{{ $sheet['present'] }} présent(s)</span> · <span class="text-amber-700">{{ $sheet['late'] }} retard(s)</span> · <span class="text-red-700">{{ $sheet['absent'] }} absent(s)</span> · {{ $sheet['excused'] }} excusé(s)</td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ $sheet['finalized_at']->format('d/m/Y à H:i') }}<br><span class="text-xs">{{ $sheet['finalizer'] ?: 'Compte supprimé' }}</span></td>
                            <td class="px-4 py-3 text-right"><a href="{{ route('attendance.report', [$sheet['type'], $sheet['id']]) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-icon name="archive" class="h-4 w-4" />Voir et imprimer</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-sm text-slate-600">Aucune fiche finalisée pour cette période.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div id="resultats" class="mt-5 overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-900">Incidents à examiner par la comptabilité</h2><p class="mt-1 text-sm text-slate-600">Absences non excusées et retards dépassant le seuil configuré.</p></div>
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
