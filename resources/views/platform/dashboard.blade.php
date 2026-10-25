<x-platform-layout title="Vue d’ensemble">
    <div class="p-page-head"><div><p class="p-kicker">Votre activité, en un regard</p><h1>Vue d’ensemble</h1><p class="p-description">Gardez le fil de vos chorales et accompagnez leur développement.</p></div><p class="p-date"><x-icon name="calendar" class="w-4 h-4" />{{ now()->format('d/m/Y') }} · {{ now()->format('H:i') }}</p></div>
    <div class="p-kpis">
        @foreach([['Toutes les chorales', $stats->total, '', 'users', 'Espaces créés sur la plateforme'], ['En essai', $stats->trial ?? 0, 'trial', 'clock', 'Découverte de l’application'], ['Accès payants actifs', $stats->paid ?? 0, 'paid', 'check-circle', 'Droits d’accès en cours'], ['Essais expirés', $stats->expired ?? 0, 'expired', 'calendar', 'En attente d’activation']] as [$label, $number, $status, $icon, $hint])
            <a class="p-kpi" href="{{ route('platform.tenants', array_filter(['status' => $status])) }}"><div class="p-kpi-top"><span>{{ $label }}</span><span class="p-icon-box"><x-icon :name="$icon" /></span></div><p class="p-kpi-value">{{ number_format($number, 0, ',', ' ') }}</p><p class="p-kpi-foot">{{ $hint }}<x-icon name="arrow-right" /></p></a>
        @endforeach
    </div>
    <div class="p-columns">
        <div class="p-stack">
            <section class="p-panel"><div class="p-panel-head"><div><h2>Dernières inscriptions</h2><p>Les cinq derniers espaces créés</p></div><a href="{{ route('platform.tenants') }}" class="p-link">Tout voir<x-icon name="arrow-right" /></a></div>
                @forelse($recent as $tenant)<a href="{{ route('platform.tenants.show', $tenant) }}" class="p-list-item"><span class="p-list-name"><span class="p-avatar">{{ mb_strtoupper(mb_substr($tenant->name, 0, 1)) }}</span><span><strong>{{ $tenant->name }}</strong><small>Créé le {{ $tenant->created_at?->format('d/m/Y') }}</small></span></span><x-platform-badge :tenant="$tenant" /></a>@empty<div class="p-empty"><x-icon name="users" /><strong>Votre première chorale arrive bientôt</strong><p>Les nouveaux espaces apparaîtront ici.</p></div>@endforelse
            </section>
            <div class="p-stat-strip"><div><span>Utilisateurs actifs · 7 jours</span><strong>{{ number_format($usage->active_users, 0, ',', ' ') }}</strong></div><div><span>Nouvelles chorales · 30 jours</span><strong>{{ number_format($signups, 0, ',', ' ') }}</strong></div><a href="{{ route('platform.tenants', ['status' => 'suspended']) }}"><span>Espaces suspendus →</span><strong>{{ $stats->suspended ?? 0 }}</strong></a></div>
        </div>
        <div class="p-stack">
            <section class="p-panel"><div class="p-panel-head"><div><h2>Répartition des espaces</h2><p>Statut actuel de vos chorales</p></div><x-icon name="chart" class="w-5 h-5 text-slate-400" /></div><div class="p-panel-body">
                @foreach(['trial' => 'En essai', 'paid' => 'Accès payants', 'expired' => 'Expirés', 'suspended' => 'Suspendus'] as $key => $label)
                    <div style="margin-bottom:17px"><div class="p-summary-row" style="margin-bottom:0"><span>{{ $label }}</span><strong>{{ $stats->$key ?? 0 }} <span class="p-muted">/ {{ $stats->total }}</span></strong></div><meter class="p-meter" min="0" max="{{ max(1, $stats->total) }}" value="{{ $stats->$key ?? 0 }}" aria-label="{{ $label }}">{{ $stats->$key ?? 0 }} sur {{ $stats->total }}</meter></div>
                @endforeach
            </div></section>
            <section class="p-panel"><div class="p-panel-head"><h2>Paiements Chariow</h2><x-icon name="banknotes" class="w-5 h-5 text-slate-400" /></div><div class="p-panel-body">
                @php($url = (string) config('tenancy.chariow.payment_url'))
                @php($paymentReady = filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https')
                <div class="p-summary-row"><span>Abonnement par chorale</span><strong>{{ \App\Services\SubscriptionPlan::price() }} / mois</strong></div>
                <div class="p-summary-row"><span>Renouvellement</span><span>Géré par Chariow</span></div>
                <div class="p-summary-row"><span>Lien de paiement</span><span class="p-badge {{ $paymentReady ? 'p-badge-paid' : 'p-badge-trial' }}">{{ $paymentReady ? 'Renseigné' : 'À configurer' }}</span></div>
                <div class="p-summary-row"><span>Vérification des licences</span><span class="p-badge {{ filled(config('tenancy.chariow.api_key')) && filled(config('tenancy.chariow.product_id')) ? 'p-badge-paid' : 'p-badge-trial' }}">{{ filled(config('tenancy.chariow.api_key')) && filled(config('tenancy.chariow.product_id')) ? 'Configurée' : 'À configurer' }}</span></div>
                <p class="p-note"><x-icon name="info" /><span>Les ventes et remboursements ne sont pas encore synchronisés. Consultez votre compte Chariow pour les montants encaissés.</span></p>
            </div></section>
        </div>
    </div>
</x-platform-layout>
