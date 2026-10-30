<x-app-layout>
    <x-page-header title="Mon carnet" icon="calendar"
        subtitle="Tout ce qui me concerne dans la chorale, au même endroit.">
        <x-slot name="actions">
            <a href="{{ route('profile.edit') }}" wire:navigate class="btn btn-secondary">
                <x-icon name="pencil" class="h-4 w-4" /> Mes informations
            </a>
        </x-slot>
    </x-page-header>

    @if(!$member)
        <x-alert type="warning" title="Profil de choriste à compléter">
            Votre compte n’est pas encore rattaché à une fiche de choriste. Complétez vos informations personnelles pour activer votre carnet.
        </x-alert>
    @else
        <section class="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 p-6 text-white shadow-lg">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-center">
                <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-2xl border-2 border-white/30 bg-white/10 text-2xl font-bold shadow-lg">
                    @if($member->photo_path)
                        <img src="{{ route('members.photo', $member) }}" alt="Photo d’identité de {{ $member->full_name }}" class="h-full w-full object-cover" />
                    @else
                        {{ $member->initials }}
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium text-amber-300">Carnet personnel de {{ $member->first_name }}</p>
                    <h2 class="mt-1 text-2xl font-bold">{{ $member->full_name }}</h2>
                    <p class="mt-2 text-sm text-slate-300">
                        {{ $member->voice?->label() ?? 'Pupitre non renseigné' }}
                        @if($member->part) · {{ $member->part }} @endif
                        @if($member->joined_on) · Choriste depuis le {{ $member->joined_on->format('d/m/Y') }} @endif
                    </p>
                </div>
                <div class="rounded-xl bg-white/10 px-5 py-3 text-center backdrop-blur">
                    <p class="text-3xl font-bold">{{ $timeline->count() }}</p>
                    <p class="text-xs text-slate-300">rendez-vous dans les {{ $notebookDays }} prochains jours</p>
                </div>
            </div>
        </section>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card label="Présences" :value="$attendance['present'] ?? 0" icon="check" color="green" />
            <x-stat-card label="Retards" :value="$attendance['late'] ?? 0" icon="clock" color="amber" />
            <x-stat-card label="Absences" :value="$attendance['absent'] ?? 0" icon="alert" color="red" />
            <x-stat-card label="Reste personnel" :value="number_format($outstanding + $incidentAmount, 0, ',', ' ').' FCFA'" icon="banknotes" color="blue" />
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                <header class="border-b border-slate-200 px-5 py-4">
                    <h2 class="font-semibold text-slate-900">Mes prochains rendez-vous</h2>
                    <p class="mt-1 text-sm text-slate-500">Répétitions, activités, célébrations et concerts auxquels je suis attendu.</p>
                </header>
                @if($timeline->isEmpty())
                    <x-empty-state icon="calendar" title="Aucun rendez-vous à venir" description="Votre calendrier personnel est à jour." />
                @else
                    <ol class="divide-y divide-slate-100">
                        @foreach($timeline as $item)
                            <li class="flex gap-4 px-5 py-4">
                                <div class="w-14 shrink-0 rounded-xl bg-slate-100 py-2 text-center">
                                    <p class="text-lg font-bold leading-none text-slate-900">{{ $item['at']->format('d') }}</p>
                                    <p class="mt-1 text-[11px] font-semibold uppercase text-slate-500">{{ $item['at']->translatedFormat('M') }}</p>
                                </div>
                                <span class="mt-1 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                                    <x-icon :name="$item['icon']" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</p>
                                    <h3 class="font-semibold text-slate-900">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ $item['at']->translatedFormat('l j F Y') }}
                                        @if($item['at']->format('H:i') !== '00:00') à {{ $item['at']->format('H:i') }} @endif
                                        @if($item['venue']) · {{ $item['venue'] }} @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>

            <div class="space-y-6">
                <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <header class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-900">Ma situation</h2></header>
                    <div class="space-y-4 p-5 text-sm">
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-600">Mois attendus depuis l’adhésion</span><strong>{{ $monthlyArrears['expected_months'] }}</strong></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-600">Mois sans cotisation enregistrée</span><strong>{{ $monthlyArrears['missing_months'] }}</strong></div>
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-600">Arriéré de cotisations</span><strong>{{ number_format($outstanding, 0, ',', ' ') }} FCFA</strong></div>
                        <div class="border-t border-slate-100 pt-3">
                            <div class="flex items-center justify-between gap-3"><span class="text-slate-600">Absences à régulariser</span><strong>{{ $incidentBreakdown['absence']['count'] ?? 0 }} · {{ number_format($incidentBreakdown['absence']['amount'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                            <div class="mt-2 flex items-center justify-between gap-3"><span class="text-slate-600">Retards à régulariser</span><strong>{{ $incidentBreakdown['late']['count'] ?? 0 }} · {{ number_format($incidentBreakdown['late']['amount'] ?? 0, 0, ',', ' ') }} FCFA</strong></div>
                        </div>
                        <div class="flex items-center justify-between gap-3"><span class="text-slate-600">Tâches confiées</span><strong>{{ $tasks->count() }}</strong></div>
                    </div>
                    @if(!$member->joined_on)
                        <p class="mx-5 mb-5 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">Renseignez la date d’adhésion pour calculer automatiquement les mois de cotisation manquants.</p>
                    @elseif($monthlyArrears['missing_months'] > 0)
                        <p class="mx-5 mb-5 text-xs text-slate-500">Les mois sans écriture sont estimés au tarif mensuel actuel de {{ number_format($monthlyArrears['default_amount'], 0, ',', ' ') }} FCFA. Les mois déjà enregistrés conservent leur montant historique.</p>
                    @endif
                </section>

                @if($tasks->isNotEmpty())
                    <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                        <header class="border-b border-slate-200 px-5 py-4"><h2 class="font-semibold text-slate-900">Mes tâches</h2></header>
                        <ul class="divide-y divide-slate-100">
                            @foreach($tasks as $task)
                                <li class="flex gap-3 px-5 py-3">
                                    <x-icon :name="$task['icon']" class="mt-0.5 h-5 w-5 shrink-0 text-slate-500" />
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900">{{ $task['title'] }}</p>
                                        <p class="truncate text-xs text-slate-600">{{ $task['detail'] }}@if($task['date']) · {{ $task['date']->format('d/m/Y') }}@endif</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <div class="flex gap-3">
                        <x-icon name="bell" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700" />
                        <div>
                            <h2 class="font-semibold text-amber-950">Ne rien manquer</h2>
                            <p class="mt-1 text-sm text-amber-900">Activez les notifications depuis la cloche en haut de l’écran pour recevoir les rappels même quand l’application est fermée.</p>
                        </div>
                    </div>
                </section>

                <a href="{{ route('members.show', $member) }}" wire:navigate class="btn btn-secondary w-full justify-center">
                    Voir mon dossier complet
                </a>
            </div>
        </div>

        <section class="mt-6 rounded-xl border border-slate-200 bg-white shadow-sm">
            <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h2 class="font-semibold text-slate-900">Courriers de la chorale</h2>
                    <p class="mt-1 text-sm text-slate-500">Les courriers adressés à toute la chorale et ceux qui vous sont personnellement destinés.</p>
                </div>
                <x-icon name="mail" class="h-5 w-5 shrink-0 text-slate-500" />
            </header>
            @if($correspondences->isEmpty())
                <x-empty-state icon="mail" title="Aucun courrier à consulter" description="Les courriers partagés et personnels apparaîtront ici." />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach($correspondences as $correspondence)
                        <li class="px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-900">{{ $correspondence->subject }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $correspondence->reference }} · {{ $correspondence->correspondence_date->format('d/m/Y') }} · {{ $correspondence->recipient_member_id ? 'Courrier personnel' : 'Courrier de la chorale' }}</p>
                                </div>
                                @if($correspondence->recipient_member_id)
                                    <span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700">Personnel</span>
                                @endif
                            </div>
                            @if($correspondence->body)
                                <p class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ $correspondence->body }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</x-app-layout>
