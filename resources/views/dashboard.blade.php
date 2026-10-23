<x-app-layout>
    <x-page-header title="Tableau de bord" icon="home"
        subtitle="Vue d'ensemble de l'activité de la chorale et de ses commissions.">
        <x-slot name="actions">
            <span class="text-sm text-slate-500">{{ now()->translatedFormat('l j F Y') }}</span>
        </x-slot>
    </x-page-header>

    {{-- Indicateurs --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @can('view-module', \App\Enums\UserRole::MODULE_MEMBERS)
            <x-stat-card label="Choristes actifs" :value="$kpis['choristes']" icon="users" color="blue"
                :href="route('members.index')" />
        @endcan

        @can('view-module', \App\Enums\UserRole::MODULE_LITURGIQUE)
            <x-stat-card label="Partitions au répertoire" :value="$kpis['partitions']" icon="book" color="purple" />
        @endcan

        @can('view-module', \App\Enums\UserRole::MODULE_COURRIERS)
            <x-stat-card label="Courriers à traiter" :value="$kpis['courriers']" icon="mail" color="amber" />
        @endcan

        @can('view-module', \App\Enums\UserRole::MODULE_COMPTABILITE)
            <x-stat-card label="Solde trésorerie" :value="number_format($kpis['solde'], 0, ',', ' ').' FCFA'"
                icon="banknotes" color="green" />
        @endcan
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        {{-- Prochaines répétitions --}}
        @can('view-module', \App\Enums\UserRole::MODULE_LITURGIQUE)
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Prochaines répétitions</h2>
                    <span class="text-xs text-slate-500">{{ $kpis['repetitions'] }} à venir</span>
                </header>

                @if ($prochainesRepetitions->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Aucune répétition planifiée.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($prochainesRepetitions as $rehearsal)
                            <li class="flex items-start gap-4 px-5 py-3">
                                <div class="w-14 shrink-0 rounded-lg bg-slate-100 py-1.5 text-center">
                                    <p class="text-lg font-semibold leading-none text-slate-900">{{ $rehearsal->date->format('d') }}</p>
                                    <p class="mt-0.5 text-[10px] font-semibold uppercase text-slate-500">{{ $rehearsal->date->translatedFormat('M') }}</p>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">
                                        {{ $rehearsal->works_count }} œuvre(s) au programme
                                    </p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $rehearsal->date->translatedFormat('l') }}
                                        @if ($rehearsal->timeRange()) — {{ $rehearsal->timeRange() }} @endif
                                        @if ($rehearsal->venue) — {{ $rehearsal->venue }} @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endcan

        {{-- Célébrations & concerts --}}
        @can('view-module', \App\Enums\UserRole::MODULE_LITURGIQUE)
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Célébrations & concerts</h2>
                    <span class="text-xs text-slate-500">{{ $kpis['concerts'] }} à venir</span>
                </header>

                @if ($prochainesCelebrations->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Aucune célébration planifiée.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($prochainesCelebrations as $performance)
                            <li class="flex items-start gap-4 px-5 py-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                                    <x-icon name="sparkles" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $performance->title }}</p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $performance->scheduled_at->translatedFormat('l j F Y à H:i') }}
                                        @if ($performance->venue) — {{ $performance->venue }} @endif
                                    </p>
                                </div>
                                <x-enum-badge :enum="$performance->type" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endcan

        {{-- Derniers courriers --}}
        @can('view-module', \App\Enums\UserRole::MODULE_COURRIERS)
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Derniers courriers</h2>
                    <span class="text-xs text-slate-500">{{ $kpis['courriers'] }} en attente</span>
                </header>

                @if ($derniersCourriers->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Aucun courrier enregistré.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($derniersCourriers as $courrier)
                            <li class="flex items-start gap-4 px-5 py-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-50 text-slate-500">
                                    <x-icon name="mail" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">
                                        <span class="font-mono text-xs text-slate-400">{{ $courrier->reference }}</span>
                                        {{ $courrier->subject }}
                                    </p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $courrier->sender_name }}
                                        @if ($courrier->service) — {{ $courrier->service->name }} @endif
                                        — {{ $courrier->correspondence_date->translatedFormat('d/m/Y') }}
                                    </p>
                                </div>
                                <x-enum-badge :enum="$courrier->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endcan

        {{-- Activités --}}
        @can('view-module', \App\Enums\UserRole::MODULE_ACTIVITES)
            <section class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <header class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-900">Activités récentes</h2>
                    <span class="text-xs text-slate-500">{{ $kpis['activites'] }} en cours</span>
                </header>

                @if ($activitesRecentes->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-slate-500">Aucune activité enregistrée.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($activitesRecentes as $activite)
                            <li class="flex items-start gap-4 px-5 py-3">
                                <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                                    <x-icon name="chart" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-slate-900">{{ $activite->title }}</p>
                                    <p class="truncate text-xs text-slate-500">
                                        {{ $activite->starts_at->translatedFormat('d/m/Y') }}
                                        @if ($activite->venue) — {{ $activite->venue }} @endif
                                    </p>
                                </div>
                                <x-enum-badge :enum="$activite->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endcan
    </div>
</x-app-layout>
