<header
    class="sticky top-0 z-30 flex min-h-16 flex-wrap items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6 lg:px-8">
    <button type="button" @click="open()" aria-controls="sidebar" :aria-expanded="sidebar ? 'true' : 'false'"
        aria-label="Ouvrir le menu"
        class="help-control -ml-1.5 inline-flex items-center gap-2 rounded-lg p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 lg:hidden">
        <x-icon name="menu" class="h-6 w-6" /><span class="text-sm font-semibold">Menu</span>
    </button>

    <div class="hidden min-w-0 items-baseline gap-2 sm:flex">
        <span class="text-sm font-semibold text-slate-800">{{ $chorale->name() }}</span>
        <span class="text-xs text-slate-400">·</span>
        <span class="text-xs font-medium text-slate-500">{{ now()->translatedFormat('l j F Y') }}</span>
    </div>

    <a href="{{ route('dashboard') }}" wire:navigate aria-label="Revenir à l’accueil"
        class="help-control inline-flex items-center gap-2 rounded-xl px-3 py-2 font-semibold text-slate-800 hover:bg-slate-100">
        <x-icon name="home" class="h-6 w-6" /><span>Accueil</span>
    </a>
    <div class="flex-1"></div>

    <x-dropdown align="right" width="48">
        <x-slot name="trigger">
            <button type="button" aria-haspopup="menu" :aria-expanded="open ? 'true' : 'false'"
                aria-label="Menu du compte"
                class="flex items-center gap-2.5 rounded-lg px-1.5 py-1.5 text-left transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-800 text-sm font-semibold text-white ring-2 ring-white">
                    {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </span>
                <span class="hidden min-w-0 md:block">
                    <span class="block truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ auth()->user()->role->label() }}</span>
                </span>
                <x-icon name="chevron-down" class="hidden h-4 w-4 text-slate-400 md:block" />
            </button>
        </x-slot>

        <x-slot name="content">
            <div class="px-3 py-2.5">
                <p class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-slate-500">Identifiant : {{ auth()->user()->username }}</p>
                <p class="mt-1.5 inline-flex items-center rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-medium text-amber-700 ring-1 ring-inset ring-amber-200">
                    {{ auth()->user()->role->label() }}
                </p>
            </div>

            <div class="my-1 border-t border-slate-100"></div>

            <x-dropdown-link :href="route('profile.edit')" wire:navigate>
                <x-icon name="user" class="h-4 w-4 text-slate-400" />
                Mon profil
            </x-dropdown-link>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-dropdown-link :href="route('logout')"
                    onclick="event.preventDefault(); this.closest('form').submit();">
                    <x-icon name="logout" class="h-4 w-4 text-slate-400" />
                    Se déconnecter
                </x-dropdown-link>
            </form>
        </x-slot>
    </x-dropdown>
</header>
