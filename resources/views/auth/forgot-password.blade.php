<x-guest-layout>
    <x-slot name="title">Mot de passe oublié</x-slot>

    <h2 class="text-lg font-semibold text-slate-900">Mot de passe oublié</h2>
    @if($deliveryEnabled)
        <p class="mt-1 text-sm text-slate-500">Indiquez l'adresse e-mail de votre compte : nous vous enverrons un lien permettant de choisir un nouveau mot de passe.</p>
    @else
        <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
            L’envoi automatique d’e-mails n’est pas encore activé. Contactez un administrateur de votre chorale : après vérification de votre identité, il pourra générer un lien personnel valable 60 minutes.
        </div>
    @endif

    @if($deliveryEnabled)
    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email"
                :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('login') }}" class="text-sm text-slate-600 underline decoration-slate-300 hover:text-slate-900">
                Retour à la connexion
            </a>
            <x-primary-button class="bg-slate-900 hover:bg-slate-700 focus:bg-slate-700">
                Envoyer le lien
            </x-primary-button>
        </div>
    </form>
    @else
        <div class="mt-6"><a href="{{ route('login') }}" class="text-sm font-semibold text-slate-700 underline decoration-slate-300 hover:text-slate-900">Retour à la connexion</a></div>
    @endif
</x-guest-layout>
