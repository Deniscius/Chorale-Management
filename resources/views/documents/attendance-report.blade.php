<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Rapport de présence — {{ $label }}</title>
<style>*{box-sizing:border-box}body{margin:0;background:#e2e8f0;color:#172033;font:14px Arial,sans-serif}.toolbar{display:flex;justify-content:center;gap:10px;padding:12px}.toolbar button,.toolbar a{border:0;border-radius:8px;padding:10px 16px;background:#0f172a;color:#fff;text-decoration:none;font-weight:700;cursor:pointer}.toolbar a{background:#475569}.page{width:210mm;min-height:297mm;margin:0 auto 24px;background:#fff;box-shadow:0 8px 30px #64748b55}.letterhead{display:block;width:210mm;height:{{ $headerHeightMm }}mm;object-fit:fill}.document-body{padding:0 16mm 18mm}.fallback{text-align:center;border-bottom:2px solid #334155;padding:12mm 16mm 5mm}.fallback h1{margin:0;font-size:23px}.fallback p{margin:2mm 0 0;color:#475569;font-size:11px}.report-title{text-align:center;margin:12mm 0 6mm}.report-title h2{margin:0;text-transform:uppercase;font-size:18px}.report-title p{margin:2mm 0 0;color:#475569}.meta{display:grid;grid-template-columns:1fr 1fr;gap:2mm 8mm;margin:0 0 6mm;padding:4mm;background:#f8fafc;border:1px solid #e2e8f0;border-radius:3mm}.meta strong{display:block;font-size:10px;text-transform:uppercase;color:#64748b;margin-bottom:1mm}.summary{display:grid;grid-template-columns:repeat(4,1fr);gap:3mm;margin-bottom:6mm}.summary div{padding:3mm;text-align:center;border:1px solid #cbd5e1;border-radius:2mm}.summary strong{display:block;font-size:18px}.summary span{font-size:10px;color:#475569}table{width:100%;border-collapse:collapse;font-size:11px}th,td{border:1px solid #cbd5e1;padding:2.5mm;text-align:left;vertical-align:top}th{background:#f1f5f9;text-transform:uppercase;font-size:9px}.attendance-list td:nth-child(1){width:7mm;text-align:center}.attendance-list td:nth-child(3){width:30mm}h2,h3{break-after:avoid}.footer{margin-top:7mm;display:flex;justify-content:space-between;gap:10mm;color:#475569;font-size:10px}.signature{min-width:55mm;padding-top:8mm;border-top:1px solid #94a3b8;text-align:center}@page{size:A4;margin:0}@media print{body{background:#fff}.toolbar{display:none}.page{width:210mm;min-height:297mm;margin:0;box-shadow:none;break-after:page}thead{display:table-header-group}tr{break-inside:avoid}}</style></head>
<body><div class="toolbar"><button type="button" onclick="window.print()">Imprimer / enregistrer en PDF</button><a href="{{ $backUrl }}">Retour</a></div><main class="page">
@if($header)<img class="letterhead" src="{{ $header }}" alt="En-tête officiel de la chorale">@else<header class="fallback"><h1>{{ $identity->name() }}</h1><p>{{ implode(' · ', array_filter([$identity->address(), $identity->phone(), $identity->email(), $identity->website()])) }}</p></header>@endif<div class="document-body">
<section class="report-title"><h2>Rapport de présence{{ $finance !== null ? ' et point financier' : '' }}</h2><p>{{ $title }} · {{ $date->format('d/m/Y') }}</p></section>
<section class="meta"><div><strong>Événement</strong>{{ $label }}</div><div><strong>Lieu</strong>{{ $venue ?: 'Non renseigné' }}</div><div><strong>État de la fiche</strong>{{ $record->attendance_finalized_at ? 'Finalisée le '.$record->attendance_finalized_at->format('d/m/Y à H:i') : 'Brouillon — rapport provisoire' }}</div><div><strong>Validée par</strong>{{ $record->attendance_finalized_at ? ($record->attendanceFinalizer?->name ?: 'Compte supprimé') : 'Non validée' }}</div></section>
@unless($record->attendance_finalized_at)<p><strong>Rapport provisoire : seules les données enregistrées sont présentées. Les présences restent à compléter et à valider.</strong></p>@endunless
<section class="summary" aria-label="Synthèse"><div><strong>{{ $counts['present'] ?? 0 }}</strong><span>Présents</span></div><div><strong>{{ $counts['late'] ?? 0 }}</strong><span>Retards</span></div><div><strong>{{ $counts['absent'] ?? 0 }}</strong><span>Absents</span></div><div><strong>{{ $counts['excuse'] ?? 0 }}</strong><span>Excusés</span></div></section>
<p>{{ $rows->count() }} choriste(s) enregistré(s) · {{ $counts['invite'] ?? 0 }} présence(s) non renseignée(s).</p>
@if($record instanceof \App\Models\Activity && $record->report)<h3>Compte rendu de l’activité</h3><p style="white-space:pre-line">{{ $record->report }}</p>@endif
<table class="attendance-list"><thead><tr><th>N°</th><th>Choriste</th><th>Statut</th><th>Observation</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row->member?->full_name ?? 'Choriste supprimé' }}</td><td>{{ $row->status->value === 'invite' ? 'Non renseigné' : ($statusLabels[$row->status->value] ?? $row->status->value) }}</td><td>@if($row->status->value === 'late')Retard de {{ $row->minutes_late }} minute(s)@elseif($row->status->value === 'excuse'){{ $row->excuse_reason }}@else—@endif</td></tr>@empty<tr><td colspan="4">Aucune présence enregistrée.</td></tr>@endforelse
</tbody></table>
@if($finance !== null)
<section aria-label="Bilan financier">
<h2>Point financier</h2>
<p>Montants en FCFA. Seules les écritures validées ou clôturées sont comptabilisées. Les participations et incidents ci-dessous sont présentés séparément et ne sont pas ajoutés aux recettes.</p>
@if($record instanceof \App\Models\Activity)
<table><tbody>
<tr><th>Budget alloué</th><td>{{ number_format((float) $record->budget_allocated, 2, ',', ' ') }}</td><th>Dépenses budgétaires déclarées</th><td>{{ number_format((float) $record->budget_spent, 2, ',', ' ') }}</td></tr>
<tr><th>Recettes validées</th><td>{{ number_format($finance['receipts'], 2, ',', ' ') }}</td><th>Dépenses validées</th><td>{{ number_format($finance['expenses'], 2, ',', ' ') }}</td></tr>
<tr><th>Solde des écritures</th><td colspan="3">{{ number_format($finance['receipts'] - $finance['expenses'], 2, ',', ' ') }}</td></tr>
</tbody></table>
<h3>Écritures de l’activité</h3>
<table><thead><tr><th>Date / référence</th><th>Libellé / choriste</th><th>Compte</th><th>Type</th><th>Montant</th></tr></thead><tbody>
@forelse($finance['transactions'] as $transaction)
<tr><td>{{ $transaction->transaction_date->format('d/m/Y') }} · {{ $transaction->reference }}</td><td>{{ $transaction->description }}<br>{{ $transaction->member?->full_name }}</td><td>{{ $transaction->account?->code }} — {{ $transaction->account?->name }}</td><td>{{ $transaction->type->label() }}</td><td>{{ number_format((float) $transaction->amount, 2, ',', ' ') }}</td></tr>
@empty<tr><td colspan="5">Aucune écriture validée pour cette activité.</td></tr>@endforelse
</tbody></table>
@include('documents.partials.member-expenses', ['memberExpenses' => $finance['advances']])
<h3>Participations financières des choristes</h3>
<p>Total dû : {{ number_format($finance['contributions']->sum('due_amount'), 2, ',', ' ') }} · Total payé : {{ number_format($finance['contributions']->sum('paid_amount'), 2, ',', ' ') }} · Reste à payer : {{ number_format($finance['contributions']->sum(fn ($contribution) => max(0, $contribution->balance())), 2, ',', ' ') }}.</p>
<table><thead><tr><th>Choriste</th><th>Montant dû</th><th>Montant payé</th><th>Reste à payer</th><th>Statut</th></tr></thead><tbody>
@forelse($finance['contributions'] as $contribution)
<tr><td>{{ $contribution->member?->full_name ?? 'Choriste supprimé' }}</td><td>{{ number_format((float) $contribution->due_amount, 2, ',', ' ') }}</td><td>{{ number_format((float) $contribution->paid_amount, 2, ',', ' ') }}</td><td>{{ number_format(max(0, $contribution->balance()), 2, ',', ' ') }}</td><td>{{ $contribution->status->label() }}</td></tr>
@empty<tr><td colspan="5">Aucune participation financière enregistrée.</td></tr>@endforelse
</tbody></table>
@endif
<h3>Incidents de présence</h3>
<p>À recouvrer : {{ number_format($finance['pending'], 2, ',', ' ') }} · Dispensés : {{ number_format($finance['waived'], 2, ',', ' ') }}. Les incidents annulés sont exclus.</p>
@unless($record->attendance_finalized_at)<p>Les incidents seront calculés lors de la finalisation de la fiche.</p>@endunless
<table><thead><tr><th>Choriste</th><th>Incident</th><th>Montant</th><th>Décision</th></tr></thead><tbody>
@forelse($finance['incidents'] as $incident)
<tr><td>{{ $incident->member?->full_name ?? 'Choriste supprimé' }}</td><td>{{ $incident->incident_type === 'late' ? 'Retard' : 'Absence' }}</td><td>{{ number_format((float) $incident->amount, 2, ',', ' ') }}</td><td>{{ $incident->status === 'waived' ? 'Dispensé' : 'À recouvrer' }}</td></tr>
@empty<tr><td colspan="4">Aucun incident de présence à signaler.</td></tr>@endforelse
</tbody></table>
</section>
@endif
<footer class="footer"><span>Document généré le {{ now()->format('d/m/Y à H:i') }}</span><span class="signature">Visa du secrétariat / de la direction</span></footer></div></main></body></html>
