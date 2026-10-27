<x-guest-layout>
    <x-slot name="title">Connexion</x-slot>

    <h2 class="text-lg font-semibold text-slate-900">Connexion à votre espace</h2>
    <p class="mt-1 text-sm text-slate-500">Saisissez votre identifiant et votre mot de passe pour accéder à la gestion de la chorale.</p>

    <!-- Session Status -->
    <x-auth-session-status class="mt-4 mb-4" :status="session('status')" />

    <form method="POST" action="{{ $loginAction ?? route('login') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="login" value="Identifiant" />
            <x-text-input id="login" class="mt-1 block w-full" type="text" name="login"
                :value="old('login')" required autocomplete="username"
                aria-describedby="login-help{{ $errors->has('login') ? ' login-error' : '' }}"
                autocapitalize="none" spellcheck="false" />
            <p id="login-help" class="mt-2 text-sm text-slate-600">Utilisez l’identifiant fourni par votre responsable.</p>
            <x-input-error id="login-error" :messages="$errors->get('login')" class="mt-2" />
        </div>

        <div x-data="{ passwordVisible: false }">
            <x-input-label for="password" value="Mot de passe" />

            <x-text-input id="password" class="mt-1 block w-full"
                type="password" x-bind:type="passwordVisible ? 'text' : 'password'"
                aria-describedby="password-help{{ $errors->has('password') ? ' password-error' : '' }}"
                name="password"
                required autocomplete="current-password" />

            <button type="button" x-cloak @click="passwordVisible = !passwordVisible"
                :aria-pressed="passwordVisible.toString()" aria-controls="password"
                class="help-control mt-2 inline-flex items-center gap-2 rounded-xl border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-800">
                <x-icon name="eye" class="h-5 w-5" />
                <span x-text="passwordVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe'">Afficher le mot de passe</span>
            </button>
            <p id="password-help" class="mt-2 text-sm text-slate-600">Gardez ce mot de passe secret. Sur un appareil partagé, ne cochez pas « Se souvenir de moi ».</p>
            <x-input-error id="password-error" :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox"
                    class="rounded border-slate-300 text-slate-900 shadow-sm focus:ring-slate-500" name="remember">
                <span class="ms-2 text-sm text-slate-600">Se souvenir de moi</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-slate-600 underline decoration-slate-300 hover:text-slate-900 hover:decoration-slate-500"
                    href="{{ route('password.request') }}">
                    Mot de passe oublié ?
                </a>
            @endif
        </div>

        <div class="flex items-center justify-end">
            <x-primary-button class="w-full justify-center bg-slate-900 hover:bg-slate-700 focus:bg-slate-700">
                Se connecter
            </x-primary-button>
        </div>
    </form>

    @if(config('tenancy.enabled') && config('tenancy.registration'))
        <p class="mt-6 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">Votre chorale découvre l’application ? <a href="{{ route('spaces.create') }}" class="font-semibold underline">Créer un espace et tester 3 jours</a></p>
    @endif
    @if(!config('tenancy.enabled') && app()->environment('local'))
    <p class="mt-6 rounded-lg bg-slate-50 p-3 text-xs leading-relaxed text-slate-500">
        <strong class="font-semibold text-slate-700">Accès démo :</strong>
        <code class="text-slate-700">admin</code> · <code class="text-slate-700">directeur</code> ·
        <code class="text-slate-700">liturgique</code> · <code class="text-slate-700">tresorerie</code><br>
        Mot de passe : <code class="text-slate-700">password</code>
    </p>
    @endif
</x-guest-layout>
