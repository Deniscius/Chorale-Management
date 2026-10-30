@props([
    'call', 'message', 'title' => 'Confirmer l’action', 'label' => 'Confirmer',
    'cancelLabel' => 'Annuler', 'buttonClass' => 'btn btn-primary',
    'triggerClass' => 'btn btn-secondary', 'icon' => null, 'iconOnly' => false,
])
<div x-data="{ open: false }" x-on:confirm-close="open = false" class="inline-flex">
    <button type="button" x-on:click="open = true" class="{{ $triggerClass }}" title="{{ $label }}" aria-label="{{ $label }}">
        @if($icon)<x-icon :name="$icon" class="h-4 w-4" />@endif
        @unless($iconOnly)<span>{{ $label }}</span>@endunless
    </button>
    <template x-if="open">
        <x-modal-panel :title="$title" :subtitle="$message" close-event="confirm-close" size="sm">
            <div class="flex justify-end gap-3">
                <button type="button" x-on:click="open = false" class="btn btn-secondary">{{ $cancelLabel }}</button>
                <button type="button" wire:click="{{ $call }}" x-on:click="open = false" wire:loading.attr="disabled" class="{{ $buttonClass }}">{{ $label }}</button>
            </div>
        </x-modal-panel>
    </template>
</div>
