<x-guest-layout>
    <x-slot name="title">Vérification de l'adresse e-mail</x-slot>

    <h2 class="text-lg font-semibold text-slate-900">Vérifiez votre adresse e-mail</h2>
    <p class="mt-1 text-sm text-slate-500">
        Merci pour votre inscription ! Avant de commencer, veuillez confirmer votre adresse e-mail en cliquant sur le lien
        qui vient de vous être envoyé. Si vous ne l'avez pas reçu, nous pouvons vous en renvoyer un.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
            Un nouveau lien de vérification a été envoyé à l'adresse e-mail que vous avez indiquée lors de votre inscription.
        </div>
    @endif

    <div class="mt-6 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-primary-button class="bg-slate-900 hover:bg-slate-700 focus:bg-slate-700">
                Renvoyer l'e-mail de vérification
            </x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="text-sm text-slate-600 underline decoration-slate-300 hover:text-slate-900">
                Se déconnecter
            </button>
        </form>
    </div>
</x-guest-layout>
