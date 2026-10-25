<x-guest-layout>
    <x-slot name="title">Mon accès</x-slot>
    <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $tenant->name }}</span>
    <h2 class="mt-2 text-2xl font-semibold text-slate-900">{{ $tenant->suspended_at ? 'Votre espace est suspendu' : ($tenant->hasAccess() ? 'Votre accès à l’application' : ($linkedSubscription ? 'Votre abonnement est à renouveler' : 'Votre essai est terminé')) }}</h2>
    <p class="mt-3 text-sm leading-6 text-slate-600">
        @if($tenant->suspended_at)
            Votre espace est suspendu. Contactez le responsable de la plateforme.
        @elseif($tenant->paid_perpetual)
            Votre accès permanent est actif.
        @elseif($tenant->paid_until?->isFuture())
            Votre accès est actif jusqu’au {{ $tenant->paid_until->format('d/m/Y à H:i') }}.
        @elseif($tenant->trial_ends_at->isFuture())
            Votre essai gratuit se termine le {{ $tenant->trial_ends_at->format('d/m/Y à H:i') }}. Un paiement sera ensuite nécessaire pour continuer.
        @else
            {{ $linkedSubscription ? 'Votre période payée est terminée.' : 'Les 72 heures d’essai sont écoulées.' }} Vos données sont conservées. Activez votre accès pour retrouver votre espace.
        @endif
    </p>
    <x-subscription-offer />
    @if(session('status'))<p role="status" class="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</p>@endif
    @if(!$tenant->suspended_at)
        @if($canManage)
            <div class="mt-6 rounded-xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-900">Continuer avec Chariow</h3>
                @if($paymentUrl)
                    <p class="mt-2 text-sm text-slate-600">Souscrivez sur Chariow à {{ \App\Services\SubscriptionPlan::price() }} par mois pour votre chorale. Les conditions du renouvellement automatique et du moyen de paiement sont confirmées sur Chariow.</p>
                    @if(!$tenant->paid_until?->isFuture() && !$tenant->paid_perpetual)
                        <a href="{{ $paymentUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex w-full justify-center rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Souscrire — {{ \App\Services\SubscriptionPlan::price() }} / mois</a>
                    @else
                        <p class="mt-3 text-sm text-slate-600">Votre période payée est active. Consultez votre espace client Chariow depuis votre reçu pour gérer le renouvellement ou la résiliation.</p>
                    @endif
                @else
                    <p class="mt-2 text-sm text-slate-600">Le paiement sera disponible prochainement. Contactez le responsable de la plateforme pour connaître les offres.</p>
                    <button disabled class="mt-4 w-full rounded-lg bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-500">Paiement bientôt disponible</button>
                @endif
            </div>
            @if($linkedSubscription && $activationReady)
                <form action="{{ route('billing.refresh') }}" method="POST" class="mt-4">
                    @csrf
                    <button type="submit" class="btn btn-secondary w-full">Actualiser mon abonnement</button>
                    <p class="mt-2 text-xs text-slate-500">L’application vérifie la licence existante. Aucun nouveau paiement n’est déclenché par cette action.</p>
                </form>
            @endif
            @if($activationReady)
                <form action="{{ route('billing.activate') }}" method="POST" class="mt-6 space-y-3">
                    @csrf
                    <x-input-label for="license_key" value="Déjà payé ? Saisissez votre clé de licence" />
                    <x-text-input id="license_key" name="license_key" type="password" required maxlength="150" autocomplete="off" class="block w-full" />
                    <x-input-error :messages="$errors->get('license_key')" />
                    <x-primary-button class="w-full justify-center">Vérifier et activer mon accès</x-primary-button>
                    <p class="text-xs text-slate-500">La licence doit correspondre à l’adresse e-mail vérifiée de votre compte.</p>
                </form>
            @endif
        @else
            <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm text-slate-700">Demandez à l’administrateur de votre chorale de régler l’accès à cet espace.</div>
        @endif
    @endif
    @if($tenant->hasAccess())<a href="{{ route('dashboard') }}" class="mt-6 block text-center text-sm font-semibold text-slate-900 underline">Retrouver mon espace</a>@endif
    <form action="{{ route('logout') }}" method="POST" class="mt-6 text-center">@csrf<button class="text-sm text-slate-500 underline">Me déconnecter</button></form>
</x-guest-layout>
