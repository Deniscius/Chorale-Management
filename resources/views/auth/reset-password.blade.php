<x-guest-layout>
    <x-slot name="title">Nouveau mot de passe</x-slot>

    <h2 class="text-lg font-semibold text-slate-900">Définir un nouveau mot de passe</h2>
    <p class="mt-1 text-sm text-slate-500">Choisissez un mot de passe d'au moins 8 caractères.</p>

    <form method="POST" action="{{ route('password.store') }}" class="mt-6 space-y-5">
        @csrf

        <!-- Password Reset Token -->
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="Adresse e-mail" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email"
                :value="old('email', $request->email)" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Nouveau mot de passe" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password"
                required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmer le mot de passe" />

            <x-text-input id="password_confirmation" class="mt-1 block w-full"
                type="password"
                name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end">
            <x-primary-button class="w-full justify-center bg-slate-900 hover:bg-slate-700 focus:bg-slate-700">
                Réinitialiser le mot de passe
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
