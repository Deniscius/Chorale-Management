@props(['title' => 'Pilotage de la plateforme'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} · Gestion Chorale</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <a href="#contenu" class="sr-only focus:not-sr-only">Aller au contenu</a>
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-5 py-5">
            <div><p class="text-xs font-bold uppercase tracking-widest text-indigo-700">Gestion Chorale · Propriétaire</p><p class="mt-1 text-lg font-semibold">Pilotage de la plateforme</p></div>
            @if(Auth::guard('platform')->check())
                <div class="flex items-center gap-4 text-sm"><span>{{ Auth::guard('platform')->user()->name }}</span><form method="POST" action="{{ route('platform.logout') }}">@csrf<button class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">Déconnexion</button></form></div>
            @endif
        </div>
        @if(Auth::guard('platform')->check())
            <nav aria-label="Administration de la plateforme" class="mx-auto flex max-w-7xl gap-2 overflow-x-auto px-5 pb-3 text-sm">
                @foreach(['dashboard' => 'Vue d’ensemble', 'tenants' => 'Chorales', 'health' => 'Supervision', 'audit' => 'Journal'] as $key => $label)
                    <a href="{{ route('platform.'.$key) }}" @if(request()->routeIs('platform.'.$key, 'platform.'.$key.'.*')) aria-current="page" @endif class="whitespace-nowrap rounded-lg px-4 py-2 {{ request()->routeIs('platform.'.$key, 'platform.'.$key.'.*') ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100' }}">{{ $label }}</a>
                @endforeach
            </nav>
        @endif
    </header>
    <main id="contenu" class="mx-auto max-w-7xl space-y-6 px-5 py-8">
        @if(session('status'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</p>@endif
        @if($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        {{ $slot }}
    </main>
    <footer class="mx-auto max-w-7xl px-5 pb-6 text-xs text-slate-500">Accès propriétaire · Les données métier privées restent protégées.</footer>
</body>
</html>
