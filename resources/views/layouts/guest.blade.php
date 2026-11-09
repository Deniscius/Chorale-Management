@inject('chorale', 'App\Services\ChoraleIdentity')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#493447">

    <title>{{ isset($title) ? $title . ' · ' : '' }}{{ $chorale->name() }}</title>

    <link rel="icon" href="{{ asset('brand-icon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body x-data="accessibilityHelp"
    @click.window="const button = $event.target.closest('[data-speak]'); if (button) read(button.dataset.speak)"
    x-on:livewire:navigating.document="stop()" @pagehide.window="stop()"
    class="min-h-screen bg-slate-50 font-sans antialiased">
    <div class="grid min-h-screen lg:grid-cols-[1.05fr_1fr]">

        <aside class="auth-brand relative hidden px-12 py-12 lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-white p-1">
                    <x-icon name="music" class="h-7 w-7 text-choir-800" />
                </span>
                <div><p class="font-semibold">{{ $chorale->name() }}</p><p class="mt-1 text-sm text-choir-200">{{ $chorale->tagline() }}</p></div>
            </div>
            <div class="my-16">
                <h2 class="auth-title">La vie du chœur se prépare ici.</h2>
                <p class="mt-7 max-w-sm text-base leading-relaxed text-choir-100">Retrouvez les répétitions, les partitions et les activités. Faites vivre votre chorale, avec chaque commission.</p>
                <div class="score-mark mt-12" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>
            </div>
            <div class="space-y-4 text-sm text-choir-200">
                @if(count($chorale->contactLines()) > 0)
                    <ul class="space-y-2">@foreach($chorale->contactLines() as $line)<li class="break-words">{{ $line['value'] }}</li>@endforeach</ul>
                @endif
                <p class="text-xs">&copy; {{ date('Y') }} {{ $chorale->name() }}<br>Accès réservé aux personnes habilitées.</p>
            </div>
        </aside>

        {{-- Formulaire --}}
        <main class="flex flex-col justify-center px-5 py-10 sm:px-12">
            <div class="mx-auto w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="flex h-11 w-11 items-center justify-center overflow-hidden rounded-xl bg-choir-800 text-white">
                        <x-icon name="music" class="h-6 w-6" />
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $chorale->name() }}</p>
                        <p class="text-xs text-slate-500">{{ $chorale->tagline() }}</p>
                    </div>
                </div>

                <div class="auth-form">
                    <x-accessibility-help :guest="true" />
                    {{ $slot }}
                </div>

                <p class="mt-6 text-center text-xs text-slate-600 lg:hidden">
                    &copy; {{ date('Y') }} {{ $chorale->name() }}
                </p>
            </div>
        </main>
    </div>
    @include('layouts.toasts')
</body>

</html>
