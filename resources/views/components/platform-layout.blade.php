@props(['title' => 'Pilotage de la plateforme', 'guest' => false])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow"><meta name="theme-color" content="#13271f">
    <title>{{ $title }} · Gestion Chorale</title>
    <link rel="icon" href="{{ asset('brand-icon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/css/platform.css', 'resources/js/platform.js'])
</head>
<body class="platform-body">
    <a href="#contenu" class="sr-only focus:not-sr-only">Aller au contenu</a>
    @if($guest)
        {{ $slot }}
    @else
    <div class="p-shell">
        <aside class="p-sidebar">
            <a class="p-brand" href="{{ route('platform.dashboard') }}"><span class="p-brand-mark"><x-icon name="music" /></span><span>Gestion Chorale<small>Espace propriétaire</small></span></a>
            <p class="p-nav-label">Votre plateforme</p>
            <nav class="p-nav" aria-label="Administration de la plateforme">
                @foreach(['dashboard' => ['Vue d’ensemble', 'chart'], 'tenants' => ['Chorales', 'users'], 'health' => ['Supervision', 'cube'], 'audit' => ['Journal', 'clock']] as $key => [$label, $icon])
                    <a href="{{ route('platform.'.$key) }}" @if(request()->routeIs('platform.'.$key, 'platform.'.$key.'.*')) aria-current="page" @endif><x-icon :name="$icon" /><span>{{ $label }}</span></a>
                @endforeach
            </nav>
            <div class="p-sidebar-note"><x-icon name="lock" class="w-5 h-5" /><p>Un espace de pilotage réservé au propriétaire de l’application.</p></div>
            @if(Auth::guard('platform')->check())<div class="p-sidebar-profile"><span class="p-avatar">{{ mb_strtoupper(mb_substr(Auth::guard('platform')->user()->name, 0, 1)) }}</span><div><strong>{{ Auth::guard('platform')->user()->name }}</strong><small>Propriétaire de la plateforme</small></div></div>@endif
        </aside>
        <div class="p-workspace">
            <header class="p-topbar"><p class="p-breadcrumb">Administration <span>/</span> <strong>{{ $title }}</strong></p><div class="p-topbar-right"><span class="p-session"><x-icon name="lock" />Session protégée</span><form method="POST" action="{{ route('platform.logout') }}">@csrf<button class="p-btn" type="submit"><x-icon name="logout" />Déconnexion</button></form></div></header>
            <main id="contenu" class="p-main">
                @if($errors->any())<div role="alert" class="p-alert p-alert-error"><x-icon name="warning" /><div><strong>Vérifiez les informations saisies.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
                {{ $slot }}
            </main>
            <footer class="p-footer">Gestion Chorale · Espace propriétaire <span aria-hidden="true">/</span> {{ now()->format('Y') }}</footer>
        </div>
    </div>
    @endif
    @include('layouts.platform-notifications')
</body>
</html>
