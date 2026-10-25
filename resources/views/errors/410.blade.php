<x-guest-layout>
    <x-slot name="title">Lien indisponible</x-slot>
    <h1 class="text-2xl font-semibold text-slate-900">Ce lien n’est plus disponible</h1>
    <p class="mt-3 text-sm text-slate-600">Il a expiré, a été remplacé ou a déjà servi à activer votre compte. Demandez un nouveau lien au responsable de votre chorale.</p>
    <a href="{{ route('login') }}" class="btn btn-primary mt-6 w-full">Aller à la connexion</a>
</x-guest-layout>
