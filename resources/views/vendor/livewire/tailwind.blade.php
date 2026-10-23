@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 py-3">
        <p class="text-sm text-slate-600">Affichage de {{ $paginator->firstItem() }} à {{ $paginator->lastItem() }} sur {{ $paginator->total() }} résultats</p>
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" wire:click="previousPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled($paginator->onFirstPage()) class="btn btn-secondary btn-sm" aria-label="Page précédente">Précédent</button>
            @foreach ($elements as $element)
                @if (is_string($element)) <span class="px-2 text-slate-500" aria-hidden="true">{{ $element }}</span> @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="btn btn-primary btn-sm">{{ $page }}</span>
                        @else
                            <button type="button" wire:key="pagination-{{ $paginator->getPageName() }}-{{ $page }}" wire:click="gotoPage({{ $page }}, '{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" class="btn btn-secondary btn-sm" aria-label="Aller à la page {{ $page }}">{{ $page }}</button>
                        @endif
                    @endforeach
                @endif
            @endforeach
            <button type="button" wire:click="nextPage('{{ $paginator->getPageName() }}')" wire:loading.attr="disabled" @disabled(!$paginator->hasMorePages()) class="btn btn-secondary btn-sm" aria-label="Page suivante">Suivant</button>
        </div>
    </nav>
@endif
