@props([
    'edit' => null,
    'remove' => null,
    'id' => null,
    'confirm' => null,
    'editLabel' => 'Modifier',
    'removeLabel' => 'Supprimer',
])

<div class="flex items-center justify-end gap-0.5">
    @if ($edit)
        <button type="button" wire:click="{{ $edit }}({{ $id }})" title="{{ $editLabel }}"
            aria-label="{{ $editLabel }}"
            class="rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-1">
            <x-icon name="pencil" class="h-4 w-4" />
        </button>
    @endif

    @if ($remove)
        @if($confirm)
            <x-confirm-action :call="$remove.'('.$id.')'" :message="$confirm" :label="$removeLabel" icon="trash" icon-only
                trigger-class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-1"
                button-class="btn bg-rose-600 text-white hover:bg-rose-700" />
        @else
            <button type="button" wire:click="{{ $remove }}({{ $id }})" title="{{ $removeLabel }}" aria-label="{{ $removeLabel }}"
                class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-1">
                <x-icon name="trash" class="h-4 w-4" />
            </button>
        @endif
    @endif
</div>
