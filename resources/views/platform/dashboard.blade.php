<x-platform-layout title="Vue d’ensemble">
    <div><h1 class="text-2xl font-semibold">Vue d’ensemble</h1><p class="mt-1 text-sm text-slate-500">Vos espaces clients et leur activité. Actualisé à chaque ouverture.</p></div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([['Toutes les chorales', $stats->total, ''], ['En essai', $stats->trial ?? 0, 'trial'], ['Accès payants actifs', $stats->paid ?? 0, 'paid'], ['Essais expirés', $stats->expired ?? 0, 'expired']] as [$label, $number, $status])
            <a class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-indigo-400" href="{{ route('platform.tenants', array_filter(['status' => $status])) }}"><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-3 text-3xl font-semibold tabular-nums">{{ $number }}</p><p class="mt-3 text-xs text-indigo-700">Voir les chorales →</p></a>
        @endforeach
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-indigo-50 p-5"><p class="text-sm text-indigo-800">Utilisateurs actifs · 7 jours</p><p class="mt-2 text-2xl font-semibold">{{ $usage->active_users }}</p></div>
        <div class="rounded-xl bg-indigo-50 p-5"><p class="text-sm text-indigo-800">Nouvelles chorales · 30 jours</p><p class="mt-2 text-2xl font-semibold">{{ $signups }}</p></div>
        <a href="{{ route('platform.tenants', ['status' => 'suspended']) }}" class="rounded-xl bg-amber-50 p-5"><p class="text-sm text-amber-800">Espaces suspendus</p><p class="mt-2 text-2xl font-semibold">{{ $stats->suspended ?? 0 }}</p></a>
    </div>
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Dernières inscriptions</h2><div class="mt-3 divide-y divide-slate-100">@forelse($recent as $tenant)<a href="{{ route('platform.tenants.show', $tenant) }}" class="flex items-center justify-between gap-3 py-3 text-sm"><span class="font-medium text-indigo-700">{{ $tenant->name }}</span><span class="rounded-full bg-slate-100 px-3 py-1 text-xs">{{ $tenant->accessLabel() }}</span></a>@empty<p class="py-5 text-sm text-slate-500">Aucune chorale pour le moment.</p>@endforelse</div></section>
        <section class="rounded-2xl border border-slate-200 bg-white p-5"><h2 class="font-semibold">Paiements Chariow</h2><p class="mt-3 text-sm leading-6 text-slate-600">{{ filled(config('tenancy.chariow.payment_url')) ? 'Lien de vente renseigné.' : 'Lien de vente à configurer.' }} {{ filled(config('tenancy.chariow.api_key')) && filled(config('tenancy.chariow.product_id')) ? 'Vérification des licences configurée.' : 'Vérification des licences à configurer.' }}</p><p class="mt-3 text-sm leading-6 text-slate-600">Les accès payants sont comptés par chorale. Les montants encaissés, remboursements et revenus récurrents ne sont pas encore synchronisés : consultez Chariow pour vos ventes.</p><p class="mt-3 text-xs text-slate-500">Les cotisations et dons des chorales ne sont jamais comptés comme vos revenus.</p></section>
    </div>
</x-platform-layout>
