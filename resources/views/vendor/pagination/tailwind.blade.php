@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination"
        class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">

        <p class="text-xs text-slate-500">
            Affichage de
            <span class="font-semibold text-slate-700">{{ $paginator->firstItem() }}</span>
            à
            <span class="font-semibold text-slate-700">{{ $paginator->lastItem() }}</span>
            sur
            <span class="font-semibold text-slate-700">{{ $paginator->total() }}</span>
            {{ $paginator->total() > 1 ? 'résultats' : 'résultat' }}
        </p>

        {{-- Version mobile : précédent / page courante / suivant --}}
        <div class="flex items-center justify-between gap-3 sm:hidden">
            @if ($paginator->onFirstPage())
                <span
                    class="btn btn-secondary btn-sm cursor-not-allowed opacity-50"
                    aria-disabled="true">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                    Précédent
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" wire:navigate.hover
                    class="btn btn-secondary btn-sm" aria-label="Page précédente">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                    Précédent
                </a>
            @endif

            <span class="whitespace-nowrap text-xs font-medium text-slate-500">
                Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}
            </span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" wire:navigate.hover
                    class="btn btn-secondary btn-sm" aria-label="Page suivante">
                    Suivant
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            @else
                <span
                    class="btn btn-secondary btn-sm cursor-not-allowed opacity-50"
                    aria-disabled="true">
                    Suivant
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </span>
            @endif
        </div>

        {{-- Version bureau : numéros de page --}}
        <div class="hidden items-center gap-1 sm:flex">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-icon-sm cursor-not-allowed opacity-50" aria-disabled="true"
                    aria-label="Page précédente">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" wire:navigate.hover
                    class="btn btn-secondary btn-icon-sm" aria-label="Page précédente">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="flex h-8 w-8 items-center justify-center text-xs font-medium text-slate-400"
                        aria-hidden="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"
                                class="flex h-8 min-w-8 items-center justify-center rounded-lg bg-slate-900 px-2 text-xs font-semibold text-white">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}" wire:navigate.hover
                                class="flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                                aria-label="Aller à la page {{ $page }}">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" wire:navigate.hover
                    class="btn btn-secondary btn-icon-sm" aria-label="Page suivante">
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            @else
                <span class="btn btn-secondary btn-icon-sm cursor-not-allowed opacity-50" aria-disabled="true"
                    aria-label="Page suivante">
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </span>
            @endif
        </div>
    </nav>
@endif
