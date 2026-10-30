<x-app-layout>
    <x-page-header :title="$member->full_name" icon="user"
        subtitle="Dossier individuel du choriste : identité, assiduité et situation financière selon vos droits.">
        <x-slot name="actions">
            <a href="{{ route('members.index') }}" wire:navigate class="btn btn-secondary">Retour aux choristes</a>
        </x-slot>
    </x-page-header>

    <section class="mb-5 flex flex-col items-center gap-5 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row">
        <div class="flex h-28 w-28 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-white bg-slate-100 text-2xl font-bold text-slate-500 shadow">
            @if($member->photo_path)
                <img src="{{ route('members.photo', $member) }}" alt="Photo d’identité de {{ $member->full_name }}" class="h-full w-full object-cover" />
            @else
                {{ $member->initials }}
            @endif
        </div>
        <div class="min-w-0 text-center sm:text-left">
            <h2 class="text-xl font-bold text-slate-900">{{ $member->full_name }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ $member->voice?->label() ?? 'Pupitre non renseigné' }}@if($member->part) · {{ $member->part }}@endif</p>
            @if(auth()->user()->member_id === $member->id)
                <a href="{{ route('profile.edit') }}" wire:navigate class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-amber-700 hover:text-amber-900">
                    <x-icon name="pencil" class="h-4 w-4" /> Modifier ma photo
                </a>
            @endif
        </div>
    </section>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Statut" :value="$member->status->label()" icon="user" color="slate" />
        <x-stat-card label="Pupitre" :value="$member->voice?->label() ?? 'Non renseigné'" icon="music" color="blue" />
        <x-stat-card label="Date d’adhésion" :value="$member->joined_on?->format('d/m/Y') ?? 'Non renseignée'" icon="calendar" color="green" />
        <x-stat-card label="Commission" :value="$member->commission ?: 'Aucune'" icon="users" color="amber" />
    </div>

    <div class="mt-5 grid gap-5 xl:grid-cols-3">
        <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-1">
            <h2 class="font-semibold text-slate-900">Informations personnelles</h2>
            <dl class="mt-4 space-y-3 text-sm">
                @foreach([
                    'Nom complet' => $member->full_name,
                    'Naissance' => $member->birth_date?->format('d/m/Y') ?? 'Non renseignée',
                    'Téléphone' => $member->phone ?: 'Non renseigné',
                    'E-mail' => $member->email ?: 'Non renseigné',
                    'Adresse' => $member->address ?: 'Non renseignée',
                    'Profession' => $member->profession ?: 'Non renseignée',
                    'Voix / partie' => trim(($member->voice?->label() ?? '').' '.($member->part ?? '')) ?: 'Non renseignée',
                ] as $label => $value)
                    <div class="border-b border-slate-100 pb-2"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</dt><dd class="mt-1 text-slate-900">{{ $value }}</dd></div>
                @endforeach
            </dl>
        </section>

        <div class="space-y-5 xl:col-span-2">
            @if($canViewAttendance)
                @php
                    $attendanceStats = $attendance->countBy(fn($row) => $row['status']->value);
                @endphp
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-5">
                        <h2 class="font-semibold text-slate-900">Présences et assiduité</h2>
                        <p class="mt-1 text-sm text-slate-600">{{ $attendanceStats['present'] ?? 0 }} présence(s), {{ $attendanceStats['late'] ?? 0 }} retard(s), {{ $attendanceStats['absent'] ?? 0 }} absence(s), {{ $attendanceStats['excuse'] ?? 0 }} absence(s) excusée(s).</p>
                    </div>
                    <div class="max-h-96 overflow-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="sticky top-0 bg-slate-50"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Événement</th><th class="px-4 py-3 text-left">Statut</th><th class="px-4 py-3 text-left">Précision</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($attendance as $row)
                                    <tr><td class="whitespace-nowrap px-4 py-3">{{ $row['date']->format('d/m/Y') }}</td><td class="px-4 py-3">{{ $row['event'] }}</td><td class="px-4 py-3"><x-enum-badge :enum="$row['status']" /></td><td class="px-4 py-3 text-slate-600">{{ $row['minutes_late'] ? $row['minutes_late'].' min de retard' : ($row['excuse_reason'] ?: '—') }}</td></tr>
                                @empty <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Aucune présence enregistrée.</td></tr> @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                @if(auth()->user()->canManageModule(\App\Enums\UserRole::MODULE_COMPTABILITE))
                    <livewire:members.contribution-exemptions :member="$member" />
                @endif

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-5">
                        <h2 class="font-semibold text-slate-900">Incidents de présence</h2>
                        <p class="mt-1 text-sm text-slate-600">Historique des absences et retards transmis à la comptabilité. Les montants restent indicatifs.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Événement</th><th class="px-4 py-3 text-left">Incident</th><th class="px-4 py-3 text-left">État</th>@if($canViewFinance)<th class="px-4 py-3 text-right">Montant</th>@endif</tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($incidents as $incident)
                                    <tr><td class="px-4 py-3">{{ $incident->occurred_on->format('d/m/Y') }}</td><td class="px-4 py-3">{{ $incident->event_label }}</td><td class="px-4 py-3">{{ $incident->incident_type === 'late' ? 'Retard de '.$incident->minutes_late.' min' : 'Absence' }}</td><td class="px-4 py-3">{{ ['pending'=>'À traiter', 'waived'=>'Dispensé', 'cancelled'=>'Annulé'][$incident->status] ?? $incident->status }}</td>@if($canViewFinance)<td class="px-4 py-3 text-right">{{ number_format((float)$incident->amount, 0, ',', ' ') }} FCFA</td>@endif</tr>
                                @empty <tr><td colspan="{{ $canViewFinance ? 5 : 4 }}" class="px-4 py-8 text-center text-slate-500">Aucun incident enregistré.</td></tr> @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($canViewFinance)
                @php
                    $due = (float) $contributions->sum('due_amount');
                    $paid = (float) $contributions->sum('paid_amount');
                    $incidentAmount = (float) $incidents->where('status', 'pending')->sum('amount');
                @endphp
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-5"><h2 class="font-semibold text-slate-900">Situation financière personnelle</h2></div>
                    <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-3">
                        <x-stat-card label="Cotisations attendues" :value="number_format($due, 0, ',', ' ').' FCFA'" icon="banknotes" color="blue" />
                        <x-stat-card label="Cotisations versées" :value="number_format($paid, 0, ',', ' ').' FCFA'" icon="check" color="green" />
                        <x-stat-card label="Reste à verser" :value="number_format(max(0, $due - $paid), 0, ',', ' ').' FCFA'" :hint="number_format($incidentAmount, 0, ',', ' ').' FCFA d’incidents indicatifs'" icon="alert" color="amber" />
                    </div>
                    <div class="overflow-x-auto border-t border-slate-100">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Période</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-right">Attendu</th><th class="px-4 py-3 text-right">Versé</th><th class="px-4 py-3 text-left">Statut</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($contributions as $contribution)
                                    <tr><td class="px-4 py-3">{{ $contribution->monthLabel() }} {{ $contribution->year }}</td><td class="px-4 py-3">{{ $contribution->typeLabel() }}</td><td class="px-4 py-3 text-right">{{ number_format((float)$contribution->due_amount, 0, ',', ' ') }}</td><td class="px-4 py-3 text-right">{{ number_format((float)$contribution->paid_amount, 0, ',', ' ') }}</td><td class="px-4 py-3"><x-enum-badge :enum="$contribution->status" /></td></tr>
                                @empty <tr><td colspan="5" class="px-4 py-8 text-center text-slate-500">Aucune cotisation enregistrée.</td></tr> @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 p-5"><h2 class="font-semibold text-slate-900">Opérations comptables liées</h2></div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left">Date</th><th class="px-4 py-3 text-left">Référence</th><th class="px-4 py-3 text-left">Libellé</th><th class="px-4 py-3 text-right">Montant</th></tr></thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($transactions as $transaction)
                                    <tr><td class="px-4 py-3">{{ $transaction->transaction_date->format('d/m/Y') }}</td><td class="px-4 py-3 font-mono text-xs">{{ $transaction->reference }}</td><td class="px-4 py-3">{{ $transaction->description }}</td><td class="px-4 py-3 text-right font-semibold">{{ number_format((float)$transaction->amount, 0, ',', ' ') }} FCFA</td></tr>
                                @empty <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">Aucune opération validée liée à ce choriste.</td></tr> @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
