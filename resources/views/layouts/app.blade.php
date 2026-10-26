@inject('chorale', 'App\Services\ChoraleIdentity')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="billing-url" content="{{ route('billing.show') }}">
    @if(config('tenancy.enabled') && ($accessEnd = request()->attributes->get('tenant')?->accessEndsAt()))
        <meta name="tenant-access-expires" content="{{ $accessEnd->toIso8601String() }}">
        <meta name="tenant-server-time" content="{{ now()->toIso8601String() }}">
    @endif
    <meta name="theme-color" content="#0f172a">

    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ $chorale->name() }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    @if ($chorale->hasLogo())
        <link rel="icon" href="{{ $chorale->logoUrl() }}">
    @else
        <link rel="icon" href="{{ asset('brand-icon.svg') }}" type="image/svg+xml">
    @endif

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 font-sans antialiased">
    <a href="#contenu"
        class="sr-only z-[200] rounded-lg bg-white px-4 py-2 text-sm font-semibold text-slate-900 shadow-lg focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:outline-none focus:ring-2 focus:ring-slate-900">
        Aller au contenu principal
    </a>

    <div x-data="{ sidebar: false }" @keydown.escape.window="sidebar = false" class="min-h-screen">
        @include('layouts.sidebar')

        <div class="flex min-h-screen flex-col lg:pl-64">
            @include('layouts.topbar')

            <main id="contenu" tabindex="-1"
                class="mx-auto w-full max-w-[1600px] flex-1 px-4 py-6 focus:outline-none sm:px-6 lg:px-8 lg:py-8">
                @if(config('tenancy.enabled') && !request()->attributes->get('tenant')?->paid_perpetual && request()->attributes->get('tenant')?->trial_ends_at?->isFuture() && !request()->attributes->get('tenant')?->paid_until?->isFuture())
                    <div class="mb-5 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                        <span>Essai gratuit · fin le {{ request()->attributes->get('tenant')->trial_ends_at->timezone(config('app.timezone'))->format('d/m/Y à H:i') }}</span>
                        <a href="{{ route('billing.show') }}" class="font-semibold underline">Mon accès</a>
                    </div>
                @endif
                @isset($header)
                    <div class="mb-6">{{ $header }}</div>
                @endisset

                {{ $slot }}
            </main>

            <footer class="mx-auto w-full max-w-[1600px] px-4 pb-6 text-xs text-slate-400 sm:px-6 lg:px-8">
                <div class="border-t border-slate-200 pt-4">
                    &copy; {{ date('Y') }} {{ $chorale->name() }} — Espace de gestion interne.
                </div>
            </footer>
        </div>

        @include('layouts.toasts')
    </div>

    @stack('scripts')
</body>

</html>
