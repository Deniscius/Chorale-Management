<x-guest-layout>
    <x-slot name="title">Vérification de l'adresse e-mail</x-slot>

    <h2 class="text-lg font-semibold text-slate-900">Vérifiez votre adresse e-mail</h2>
    <p class="mt-1 text-sm text-slate-500">
        Votre essai est accessible sans confirmation par e-mail. Cette vérification est nécessaire uniquement pour
        activer une licence payante. Demandez un lien, puis ouvrez-le depuis votre boîte mail.
    </p>

    <div class="mt-6 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button class="bg-slate-900 hover:bg-slate-700 focus:bg-slate-700">
                Envoyer le lien de vérification
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm text-slate-600 underline decoration-slate-300 hover:text-slate-900">
                Se déconnecter
            </button>
        </form>
    </div>
    <a href="{{ route('dashboard') }}" class="mt-5 block text-center text-sm font-semibold text-slate-900 underline">Retourner à mon espace</a>
</x-guest-layout>
