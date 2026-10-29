<x-guest-layout>
    <x-slot name="title">Créer mon espace</x-slot>
    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800">3 jours pour découvrir</span>
    <h2 class="mt-4 text-2xl font-semibold text-slate-900">Un espace pour votre chorale</h2>
    <p class="mt-2 text-sm leading-6 text-slate-600">Testez l’application pendant 72 heures, avec un accès immédiat sans confirmation par e-mail. Vos données restent dans votre espace. Aucun paiement automatique à la fin de l’essai.</p>
    <x-subscription-offer />
    <form method="POST" action="{{ route('spaces.store') }}" class="mt-6 space-y-4">
        @csrf
        <div hidden aria-hidden="true">
            <label for="website">Laisser ce champ vide</label>
            <input id="website" name="website" type="text" value="" tabindex="-1" autocomplete="off">
        </div>
        @if($churchProjectEnabled)
        <div>
            <x-input-label for="invitation" value="Invitation Church Project" />
            <x-text-input id="invitation" name="invitation" :value="old('invitation', request('invitation'))" class="mt-1 block w-full font-mono" required minlength="40" maxlength="200" autocomplete="off" autofocus />
            <p class="mt-1 text-xs text-slate-500">Ce code à usage unique est fourni par votre paroisse depuis Church Project.</p>
            <x-input-error :messages="$errors->get('invitation')" class="mt-1" />
        </div>
        @else
        <div>
            <x-input-label for="organization" value="Nom de votre chorale" />
            <x-text-input id="organization" name="organization" :value="old('organization')" class="mt-1 block w-full" required maxlength="120" autocomplete="organization" autofocus />
            <x-input-error :messages="$errors->get('organization')" class="mt-1" />
        </div>
        @endif
        <div>
            <x-input-label for="name" value="Votre nom" />
            <x-text-input id="name" name="name" :value="old('name')" class="mt-1 block w-full" required maxlength="120" autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="email" value="Votre adresse e-mail" />
            <x-text-input id="email" name="email" type="email" :value="old('email')" class="mt-1 block w-full" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="username" value="Votre identifiant de connexion" />
            <x-text-input id="username" name="username" :value="old('username')" class="mt-1 block w-full" required maxlength="50" autocomplete="username" autocapitalize="none" spellcheck="false" />
            <x-input-error :messages="$errors->get('username')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="password" value="Mot de passe" />
            <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required minlength="10" autocomplete="new-password" />
            <p class="mt-1 text-xs text-slate-500">Au moins 10 caractères, avec des lettres et des chiffres.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirmer le mot de passe" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required autocomplete="new-password" />
        </div>
        <label class="flex items-start gap-3 text-sm leading-5 text-slate-600">
            <input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-slate-300" @checked(old('terms'))>
            <span>J’ai compris qu’après les 72 heures d’essai, l’abonnement coûte {{ \App\Services\SubscriptionPlan::price() }} par mois pour ma chorale. La souscription et l’autorisation du renouvellement se font sur Chariow.</span>
        </label>
        <x-input-error :messages="$errors->get('terms')" />
        <x-primary-button class="w-full justify-center">Démarrer mon essai gratuit</x-primary-button>
    </form>
    <p class="mt-5 text-center text-sm text-slate-500">Déjà un compte ? <a href="{{ route('login') }}" class="font-semibold text-slate-900 underline">Me connecter</a></p>
</x-guest-layout>
