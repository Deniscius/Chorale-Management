<x-app-layout>
    <x-page-header title="Centre de rapports" icon="archive" subtitle="Documents officiels par service, filtrables et imprimables avec le papier à en-tête de la chorale." />

    @if($commissions->isNotEmpty())
        <section class="mb-6"><h2 class="text-lg font-semibold text-slate-900">Mes commissions</h2><p class="mt-1 text-sm text-slate-600">Chaque rapport est limité à la commission que vous dirigez ou dont vous êtes l’associé.</p><div class="mt-3 report-directory">@foreach($commissions as $commission)<article class="report-entry"><p class="text-xs font-semibold text-choir-700">{{ \App\Models\ChoirCommission::moduleLabels()[$commission->module_key] ?? 'Commission' }}</p><h3 class="mt-2 font-semibold text-slate-900">{{ $commission->name }}</h3><p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $commission->mission }}</p><a href="{{ route('reports.commission', $commission) }}" target="_blank" class="btn btn-primary mt-4">Générer le rapport</a></article>@endforeach</div></section>
    @endif
    <div class="report-directory">
        @if($canActivities || $canAccounting || auth()->user()->can('view-attendance'))
            <section class="report-entry"><h2 class="text-xl font-semibold text-slate-900">Rapport complet d’une activité</h2><p class="mt-1 text-sm text-slate-600">Informations, participants, présences, compte rendu et point financier selon vos droits, avec le papier à en-tête officiel.</p><a href="{{ route('activities.index') }}" wire:navigate class="btn btn-secondary mt-4">Choisir une activité</a></section>
        @endif
        @if($canActivities || $canAccounting)
            <section class="report-entry"><h2 class="text-xl font-semibold text-slate-900">Activités et finances</h2><p class="mt-1 text-sm text-slate-600">Agenda réalisé, budgets, participations et écritures validées pour une période.</p><form action="{{ route('activities.report') }}" method="get" target="_blank" class="mt-4 grid gap-2"><input type="date" name="from" value="{{ now()->startOfYear()->format('Y-m-d') }}" class="form-input" aria-label="Début"><input type="date" name="to" value="{{ now()->format('Y-m-d') }}" class="form-input" aria-label="Fin"><select aria-label="Orientation du document" name="orientation" class="form-select"><option value="landscape">Format paysage</option><option value="portrait">Format portrait</option></select><button class="btn btn-primary" type="submit">Générer le rapport</button></form></section>
        @endif
        @if($canMembers)
            <section class="report-entry"><h2 class="text-xl font-semibold text-slate-900">Choristes</h2><p class="mt-1 text-sm text-slate-600">Liste officielle, statuts et répartition par pupitre.</p><a href="{{ route('members.report') }}" target="_blank" class="btn btn-secondary mt-4">Ouvrir le rapport</a></section>
        @endif
        @if($canAttendanceFinance)
            <section class="report-entry"><h2 class="text-xl font-semibold text-slate-900">Présences et incidences</h2><p class="mt-1 text-sm text-slate-600">Absences et retards issus uniquement des fiches finalisées.</p><a href="{{ route('attendance.financial-report') }}" class="btn btn-secondary mt-4">Consulter le rapport</a></section>
        @endif
        @if($canAccounting)
            <section class="report-entry"><h2 class="text-xl font-semibold text-slate-900">Comptabilité</h2><p class="mt-1 text-sm text-slate-600">Synthèse de l’exercice, cotisations, dons et opérations.</p><a href="{{ route('accounting.dashboard') }}" class="btn btn-secondary mt-4">Voir la synthèse</a></section>
        @endif
        @if($canAudit)
            <section class="report-entry"><h2 class="text-xl font-semibold text-slate-900">Traçabilité</h2><p class="mt-1 text-sm text-slate-600">Journal horodaté des créations, modifications et actions sensibles.</p><a href="{{ route('audit.index') }}" class="btn btn-secondary mt-4">Ouvrir le journal</a></section>
        @endif
    </div>
</x-app-layout>
