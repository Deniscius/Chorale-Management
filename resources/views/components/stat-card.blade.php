@props([
    'label',
    'value',
    'icon' => 'chart',
    'hint' => null,
    'color' => 'blue',
    'href' => null,
])

@php
    $tones = [
        'slate' => ['bg-slate-100', 'text-slate-600'],
        'gray' => ['bg-gray-100', 'text-gray-600'],
        'blue' => ['bg-blue-50', 'text-blue-600'],
        'indigo' => ['bg-indigo-50', 'text-indigo-600'],
        'violet' => ['bg-violet-50', 'text-violet-600'],
        'purple' => ['bg-purple-50', 'text-purple-600'],
        'pink' => ['bg-pink-50', 'text-pink-600'],
        'rose' => ['bg-rose-50', 'text-rose-600'],
        'red' => ['bg-red-50', 'text-red-600'],
        'orange' => ['bg-orange-50', 'text-orange-600'],
        'amber' => ['bg-amber-50', 'text-amber-600'],
        'yellow' => ['bg-yellow-50', 'text-yellow-600'],
        'lime' => ['bg-lime-50', 'text-lime-600'],
        'green' => ['bg-green-50', 'text-green-600'],
        'emerald' => ['bg-emerald-50', 'text-emerald-600'],
        'teal' => ['bg-teal-50', 'text-teal-600'],
        'cyan' => ['bg-cyan-50', 'text-cyan-600'],
        'sky' => ['bg-sky-50', 'text-sky-600'],
    ];
    [$iconBg, $iconText] = $tones[$color] ?? $tones['blue'];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif
    class="group block h-full rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition @if ($href) hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-900/30 focus-visible:ring-offset-2 @endif">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="truncate text-sm font-medium text-slate-500">{{ $label }}</p>
            <p class="mt-2 text-2xl font-semibold tracking-tight text-slate-900 tabular-nums sm:text-3xl">
                {{ $value }}
            </p>
            @if ($hint)
                <p class="mt-1.5 truncate text-xs text-slate-500">{{ $hint }}</p>
            @endif
        </div>

        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $iconBg }} {{ $iconText }}">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    </div>
</{{ $tag }}>
