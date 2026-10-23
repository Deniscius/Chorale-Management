<x-guest-layout>
    <x-slot name="title">Confirmation du mot de passe</x-slot>

    <h2 class="text-lg font-semibold text-slate-900">Zone sécurisée</h2>
    <p class="mt-1 text-sm text-slate-500">
        Veuillez confirmer votre mot de passe avant de poursuivre.
    </p>

    <form method="POST" action="{{ route('password.confirm') }}" class="mt-6 space-y-5">
        @csrf

        <div>
            <x-input-label for="password" value="Mot de passe" />

            <x-text-input id="password" class="mt-1 block w-full"
                type="password"
                name="password"
                required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex justify-end">
            <x-primary-button class="bg-slate-900 hover:bg-slate-700 focus:bg-slate-700">
                Confirmer
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
