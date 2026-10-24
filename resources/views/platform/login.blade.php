<x-platform-layout title="Connexion propriétaire">
    <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="text-2xl font-semibold">Votre espace propriétaire</h1>
        <p class="mt-2 text-sm leading-6 text-slate-600">Gérez vos clients et l’état de votre application. Utilisez votre compte propriétaire et le code de votre application d’authentification.</p>
        <form action="{{ route('platform.login.store') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div><x-input-label for="email" value="Adresse e-mail propriétaire" /><x-text-input id="email" name="email" type="email" :value="old('email')" required autocomplete="username" class="mt-1 block w-full" /></div>
            <div><x-input-label for="password" value="Mot de passe" /><x-text-input id="password" name="password" type="password" required autocomplete="current-password" class="mt-1 block w-full" /></div>
            <div><x-input-label for="code" value="Code de sécurité à 6 chiffres" /><x-text-input id="code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" class="mt-1 block w-full" /></div>
            <x-primary-button class="w-full justify-center">Accéder au pilotage</x-primary-button>
        </form>
        <p class="mt-5 text-xs leading-5 text-slate-500">Création et récupération du compte depuis le terminal sécurisé du serveur. Aucun compte de chorale ne donne accès à cet espace.</p>
    </div>
</x-platform-layout>
