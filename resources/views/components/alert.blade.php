@props([
    'tone' => 'info',
    'title' => null,
    'icon' => null,
])

@php
    $tones = [
        'success' => ['alert-success', 'check-circle', 'text-emerald-500'],
        'info' => ['alert-info', 'info', 'text-sky-500'],
        'warning' => ['alert-warning', 'warning', 'text-amber-500'],
        'danger' => ['alert-danger', 'x-circle', 'text-rose-500'],
    ];
    [$classes, $defaultIcon, $iconColor] = $tones[$tone] ?? $tones['info'];
@endphp

<div {{ $attributes->merge(['class' => 'alert ' . $classes]) }} role="{{ $tone === 'danger' ? 'alert' : 'status' }}">
    <x-icon :name="$icon ?? $defaultIcon" class="mt-0.5 h-5 w-5 shrink-0 {{ $iconColor }}" />

    <div class="min-w-0 flex-1">
        @if ($title)
            <p class="font-semibold">{{ $title }}</p>
        @endif
        <div @class(['text-sm', 'mt-0.5' => $title])>{{ $slot }}</div>
    </div>

    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</div>
