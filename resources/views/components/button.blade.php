@props([
    'variant' => 'primary',
    'size' => 'md',
    'icon' => null,
    'type' => 'button',
    'href' => null,
    'loading' => null,
    'loadingText' => 'Enregistrement…',
])

@php
    $variants = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'accent' => 'btn-accent',
        'danger' => 'btn-danger',
        'ghost' => 'btn-ghost',
        'soft' => 'btn-soft',
    ];
    $sizes = [
        'sm' => 'btn-sm',
        'md' => '',
        'lg' => 'btn-lg',
        'icon' => 'btn-icon',
        'icon-sm' => 'btn-icon-sm',
    ];
    $classes = trim('btn ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? ''));
    $isLoading = filled($loading);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" @if ($isLoading) wire:loading.attr="disabled" @endif
        {{ $attributes->merge(['class' => $classes]) }}>
        @if ($isLoading)
            <span wire:loading.remove wire:target="{{ $loading }}" class="inline-flex items-center gap-2">
                @if ($icon)
                    <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
                @endif
                {{ $slot }}
            </span>
            <span wire:loading wire:target="{{ $loading }}" class="inline-flex items-center gap-2">
                <svg class="h-4 w-4 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" />
                    <path class="opacity-90" fill="currentColor"
                        d="M12 2a10 10 0 0 1 10 10h-3a7 7 0 0 0-7-7V2z" />
                </svg>
                <span>{{ $loadingText }}</span>
            </span>
        @else
            @if ($icon)
                <x-icon :name="$icon" class="h-4 w-4 shrink-0" />
            @endif
            {{ $slot }}
        @endif
    </button>
@endif
