@php
    use App\Enums\UserRole;

    $sections = [
        [
            'label' => 'Pilotage',
            'items' => [
                ['route' => 'dashboard', 'label' => 'Tableau de bord', 'icon' => 'home'],
            ],
        ],
        [
            'label' => 'La chorale',
            'items' => [
                ['route' => 'members.index', 'label' => 'Choristes', 'icon' => 'users', 'module' => UserRole::MODULE_MEMBERS],
            ],
        ],
        [
            'label' => 'Commission liturgique',
            'items' => [
                ['route' => 'documents.index', 'parameters'=>['commission'=>'commission_liturgique'], 'label'=>'Archives liturgiques', 'icon'=>'archive'],
                ['route' => 'works.index', 'label' => 'Œuvres & partitions', 'icon' => 'book', 'module' => UserRole::MODULE_LITURGIQUE],
                ['route' => 'rehearsals.index', 'label' => 'Répétitions', 'icon' => 'music', 'module' => UserRole::MODULE_LITURGIQUE],
                ['route' => 'performances.index', 'label' => 'Célébrations & concerts', 'icon' => 'sparkles', 'module' => UserRole::MODULE_LITURGIQUE],
                ['route' => 'seasons.index', 'label' => 'Temps liturgiques', 'icon' => 'calendar', 'module' => UserRole::MODULE_LITURGIQUE],
                ['route' => 'reminders.index', 'label' => 'Rappels', 'icon' => 'bell', 'module' => UserRole::MODULE_LITURGIQUE],
            ],
        ],
        [
            'label' => 'Commission des instruments',
            'items' => [
                ['route' => 'instruments.index', 'label' => 'Inventaire', 'icon' => 'cube', 'module' => UserRole::MODULE_INSTRUMENTS],
                ['route' => 'instruments.movements', 'label' => 'Mouvements', 'icon' => 'folder', 'module' => UserRole::MODULE_INSTRUMENTS],
                ['route' => 'instruments.maintenances', 'label' => 'Maintenance', 'icon' => 'wrench', 'module' => UserRole::MODULE_INSTRUMENTS],
                ['route' => 'instruments.ensembles', 'label' => 'Ensembles', 'icon' => 'music', 'module' => UserRole::MODULE_INSTRUMENTS],
            ],
        ],
        [
            'label' => 'Secrétariat',
            'items' => [
                ['route' => 'documents.index', 'parameters'=>['commission'=>'commission_secretariat'], 'label'=>'Archives des courriers', 'icon'=>'archive'],
                ['route' => 'correspondences.index', 'label' => 'Courriers', 'icon' => 'mail', 'module' => UserRole::MODULE_COURRIERS],
                ['route' => 'activities.index', 'label' => 'Activités', 'icon' => 'chart', 'module' => UserRole::MODULE_ACTIVITES],
                ['route' => 'documents.index', 'label' => 'Archives documentaires', 'icon' => 'archive'],
            ],
        ],
        [
            'label' => 'Comptabilité',
            'items' => [
                ['route' => 'transactions.index', 'label' => 'Recettes & dépenses', 'icon' => 'banknotes', 'module' => UserRole::MODULE_COMPTABILITE],
                ['route' => 'budgets.index', 'label' => 'Budgets', 'icon' => 'chart', 'module' => UserRole::MODULE_COMPTABILITE],
                ['route' => 'contributions.index', 'label' => app(\App\Services\FinancialCatalog::class)->section('contribution'), 'icon' => 'users', 'module' => UserRole::MODULE_COMPTABILITE],
                ['route' => 'donations.index', 'label' => app(\App\Services\FinancialCatalog::class)->section('donation'), 'icon' => 'sparkles', 'module' => UserRole::MODULE_COMPTABILITE],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['label' => 'Journal des modifications', 'route' => 'audit.index', 'icon' => 'clock', 'admin' => true],
                ['route' => 'users.index', 'label' => 'Utilisateurs', 'icon' => 'cog', 'admin' => true],
                ['route' => 'settings.financial-types', 'label' => 'Types de contribution', 'icon' => 'banknotes', 'admin' => true],
                ['route' => 'settings.chorale', 'label' => 'Informations de la chorale', 'icon' => 'building', 'admin' => true],
            ],
        ],
    ];
@endphp

<div x-cloak x-show="sidebar" class="fixed inset-0 z-40 bg-slate-900/70 backdrop-blur-sm lg:hidden"
    @click="sidebar = false" aria-hidden="true"></div>

<aside id="sidebar" :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-900 transition-transform duration-200 lg:translate-x-0 lg:w-64"
    aria-label="Navigation principale">

    <div class="flex h-16 shrink-0 items-center gap-3 border-b border-slate-800/80 px-5">
        <span class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-amber-400 text-slate-900 shadow-sm shadow-amber-400/20">
            <x-chorale-logo icon-class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-white">{{ $chorale->name() }}</p>
            <p class="truncate text-xs text-slate-400">{{ $chorale->tagline() }}</p>
        </div>
        <button type="button" @click="sidebar = false" aria-label="Fermer le menu"
            class="ml-auto -mr-1.5 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-800 hover:text-white lg:hidden">
            <x-icon name="x" class="h-5 w-5" />
        </button>
    </div>

    <nav class="scrollbar-slim flex-1 space-y-6 overflow-y-auto px-3 py-5" aria-label="Sections">
        @foreach ($sections as $section)
            @php
                $visible = collect($section['items'])->filter(function ($item) {
                    if ($item['admin'] ?? false) {
                        return auth()->user()?->can('manage-users');
                    }

                    if (isset($item['module'])) {
                        return auth()->user()?->canAccessModule($item['module']);
                    }

                    return true;
                });

                $visible = $visible->filter(fn($item) => Route::has($item['route']));
            @endphp

            @if ($visible->isNotEmpty())
                <section>
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                        {{ $section['label'] }}
                    </p>
                    <ul class="space-y-1">
                        @foreach ($visible as $item)
                            @php
                                $active = request()->routeIs($item['route']) || request()->routeIs($item['route'] . '*');
                                if ($item['route'] === 'documents.index') {
                                    $active = $active && request()->query('commission', '') === ($item['parameters']['commission'] ?? '');
                                }
                            @endphp
                            <li>
                                <a href="{{ route($item['route'], $item['parameters'] ?? []) }}" wire:navigate.hover
                                    aria-current="{{ $active ? 'page' : 'false' }}"
                                    @class([
                                        'nav-item',
                                        'nav-item-active' => $active,
                                        'nav-item-idle' => ! $active,
                                    ])>
                                    <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                                    <span class="truncate">{{ $item['label'] }}</span>
                                    @if ($active)
                                        <span class="ml-auto h-1.5 w-1.5 rounded-full bg-amber-400" aria-hidden="true"></span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endforeach
    </nav>

    <div class="shrink-0 border-t border-slate-800/80 p-4">
        <p class="text-xs text-slate-500">
            Gestion de la chorale
        </p>
    </div>
</aside>
