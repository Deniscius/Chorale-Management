<x-app-layout>
    <x-page-header title="Mon profil" icon="user" subtitle="Mes informations personnelles et mes responsabilités dans la chorale." />

    <div class="py-8">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-4 sm:p-8" aria-labelledby="my-responsibilities-title">
                <h2 id="my-responsibilities-title" class="text-lg font-semibold text-slate-900">Mes responsabilités actives</h2>
                <p class="mt-2 text-sm text-slate-700">Appartenance à la chorale : choriste. Rôle général : <strong>{{ $user->role->label() }}</strong>.</p>
                <ul class="mt-3 space-y-2 text-sm text-slate-800">
                    @forelse($commissions as $commission)
                        <li><strong>{{ (int)$commission->leader_id === $user->id ? 'Responsable' : ($delegations->get($commission->id)?->is_instrument_associate ? 'Associé habilité' : ((int)$commission->associate_id === $user->id ? 'Associé désigné aux archives' : 'Membre')) }}</strong> — {{ $commission->name }}</li>
                    @empty
                        <li>Aucune affectation de commission active pour le moment.</li>
                    @endforelse
                </ul>
                <p class="mt-3 text-sm text-slate-700">Ces affectations sont gérées par un administrateur. Elles ne se modifient pas dans vos informations personnelles.</p>
            </section>

            <div class="p-4 sm:p-8 bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.photo-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow-sm ring-1 ring-slate-200 sm:rounded-xl">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
