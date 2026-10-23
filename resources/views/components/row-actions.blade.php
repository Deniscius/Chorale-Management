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
        <button type="button" wire:click="{{ $remove }}({{ $id }})"
            @if ($confirm) wire:confirm="{{ $confirm }}" @endif
            title="{{ $removeLabel }}" aria-label="{{ $removeLabel }}"
            class="rounded-lg p-2 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:ring-offset-1">
            <x-icon name="trash" class="h-4 w-4" />
        </button>
    @endif
</div>
