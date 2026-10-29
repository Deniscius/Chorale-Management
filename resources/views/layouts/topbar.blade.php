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

    <div class="relative" x-data="notificationCenter({
        index: @js(route('notifications.index')),
        read: @js(route('notifications.read', ['notification' => '__ID__'])),
        readAll: @js(route('notifications.read-all')),
        subscribe: @js(route('notifications.push.store')),
        unsubscribe: @js(route('notifications.push.destroy'))
    })" @keydown.escape.window="open = false">
        <button type="button" @click="open = !open; if (open) refresh(false)" :aria-expanded="open ? 'true' : 'false'"
            aria-haspopup="dialog" aria-label="Ouvrir les notifications"
            class="help-control relative inline-flex h-11 w-11 items-center justify-center rounded-xl text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
            <x-icon name="bell" class="h-5 w-5" />
            <span x-cloak x-show="unread > 0" x-text="unread > 99 ? '99+' : unread"
                class="absolute right-0.5 top-0.5 min-w-5 rounded-full bg-rose-600 px-1 text-center text-[10px] font-bold leading-5 text-white"></span>
        </button>
        <div x-cloak x-show="open" x-transition.origin.top.right @click.outside="open = false"
            class="fixed inset-x-3 top-16 z-50 max-h-[75vh] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl sm:absolute sm:inset-x-auto sm:right-0 sm:top-12 sm:w-96"
            role="dialog" aria-label="Centre de notifications">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                <div><p class="font-semibold text-slate-900">Notifications</p><p class="text-xs text-slate-500">Bonjour, {{ auth()->user()->name }}</p></div>
                <button type="button" x-show="unread > 0" @click="readAll()" class="text-xs font-semibold text-indigo-700 hover:underline">Tout marquer comme lu</button>
            </div>
            <div class="max-h-80 overflow-y-auto overscroll-contain">
                <p x-show="loading" class="p-6 text-center text-sm text-slate-500">Actualisation…</p>
                <p x-show="!loading && items.length === 0" class="p-6 text-center text-sm text-slate-500">Vous êtes à jour.</p>
                <template x-for="item in items" :key="item.id">
                    <button type="button" @click="read(item)" class="block w-full border-b border-slate-100 px-4 py-3 text-left transition hover:bg-indigo-50 focus-visible:bg-indigo-50 focus-visible:outline-none" :class="item.read ? 'bg-white' : 'bg-indigo-50/50'">
                        <span class="flex items-start gap-3"><span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="item.read ? 'bg-slate-200' : 'bg-indigo-600'"></span><span class="min-w-0"><span class="block text-sm font-semibold text-slate-900" x-text="item.title"></span><span class="mt-0.5 block text-sm text-slate-600" x-text="item.message"></span><span class="mt-1 block text-xs text-slate-400" x-text="item.created_at"></span></span></span>
                    </button>
                </template>
            </div>
            <div class="border-t border-slate-100 bg-slate-50 p-3">
                <button type="button" x-show="!push.subscribed" @click="enablePush()" class="btn btn-primary w-full justify-center"><x-icon name="bell" class="h-4 w-4" /> Recevoir les rappels sur cet appareil</button>
                <button type="button" x-show="push.subscribed" @click="disablePush()" class="btn btn-soft w-full justify-center">Notifications de l’appareil activées</button>
                <p class="mt-2 text-center text-[11px] text-slate-500">Les rappels peuvent arriver même lorsque le site est fermé.</p>
            </div>
        </div>
    </div>

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
