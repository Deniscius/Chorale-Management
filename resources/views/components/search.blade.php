@props([
    'name' => 'search',
    'label' => null,
    'placeholder' => 'Rechercher…',
    'target' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
@endphp

<div class="relative">
    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
        <x-icon name="search" class="h-4 w-4" />
    </span>

    <input id="{{ $id }}" name="{{ $name }}" type="search" placeholder="{{ $placeholder }}"
        aria-label="{{ $label ?? $placeholder }}"
        {{ $attributes->except(['id', 'name'])->merge(['class' => 'form-input form-input-icon pr-9']) }} />

    @if ($target)
        <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
            <svg wire:loading wire:target="{{ $target }}" class="h-4 w-4 animate-spin text-slate-400"
                viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                <path class="opacity-90" fill="currentColor" d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 0 0-7-7V2z" />
            </svg>
        </span>
    @endif
</div>
