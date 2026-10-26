@inject('chorale', 'App\Services\ChoraleIdentity')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">

    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ $chorale->name() }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <link rel="icon" href="{{ asset('brand-icon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 font-sans antialiased">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

        {{-- Panneau de marque (desktop) --}}
        <aside class="relative hidden overflow-hidden bg-slate-900 px-12 py-12 lg:flex lg:flex-col lg:justify-between">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-amber-400/10 blur-3xl"
                aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-24 h-96 w-96 rounded-full bg-sky-400/10 blur-3xl"
                aria-hidden="true"></div>

            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-amber-400 text-slate-900">
                        <x-icon name="music" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-white">{{ $chorale->name() }}</p>
                        <p class="text-xs text-slate-400">{{ $chorale->tagline() }}</p>
                    </div>
                </div>

                <h2 class="mt-16 max-w-md text-balance text-3xl font-semibold leading-tight tracking-tight text-white">
                    Toute la vie de la chorale, réunie en un seul espace.
                </h2>
                <p class="mt-4 max-w-md text-pretty text-sm leading-relaxed text-slate-400">
                    Choristes, liturgie, instruments, courriers, activités et trésorerie : les commissions travaillent
                    ensemble, avec des données fiables et à jour.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-slate-300">
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-amber-400">
                            <x-icon name="check" class="h-3.5 w-3.5" />
                        </span>
                        Suivi des choristes et de leurs voix
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-amber-400">
                            <x-icon name="check" class="h-3.5 w-3.5" />
                        </span>
                        Répertoire et célébrations
                    </li>
                    <li class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-amber-400">
                            <x-icon name="check" class="h-3.5 w-3.5" />
                        </span>
                        Cotisations, dons et budgets
                    </li>
                </ul>
            </div>

            <div class="relative space-y-3">
                @if (count($chorale->contactLines()) > 0)
                    <ul class="space-y-2 text-sm text-slate-300">
                        @foreach ($chorale->contactLines() as $line)
                            <li class="flex items-center gap-3">
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-800 text-amber-400">
                                    <x-icon :name="$line['icon']" class="h-3.5 w-3.5" />
                                </span>
                                <span class="min-w-0 truncate">{{ $line['value'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <p class="text-xs text-slate-500">
                    &copy; {{ date('Y') }} {{ $chorale->name() }} — Accès réservé aux membres habilités.
                </p>
            </div>
        </aside>

        {{-- Formulaire --}}
        <main class="flex flex-col justify-center px-4 py-10 sm:px-8">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-slate-900 text-amber-400">
                        <x-icon name="music" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $chorale->name() }}</p>
                        <p class="text-xs text-slate-500">{{ $chorale->tagline() }}</p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-slate-400 lg:hidden">
                    &copy; {{ date('Y') }} {{ $chorale->name() }}
                </p>
            </div>
        </main>
    </div>
</body>

</html>
