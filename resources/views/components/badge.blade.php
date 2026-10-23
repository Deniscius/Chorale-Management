@props([
    'tone' => 'slate',
    'dot' => false,
])

@php
    $tones = [
        'slate' => 'bg-slate-100 text-slate-700',
        'gray' => 'bg-gray-100 text-gray-700',
        'emerald' => 'bg-emerald-100 text-emerald-800',
        'green' => 'bg-green-100 text-green-800',
        'amber' => 'bg-amber-100 text-amber-800',
        'orange' => 'bg-orange-100 text-orange-800',
        'rose' => 'bg-rose-100 text-rose-800',
        'red' => 'bg-red-100 text-red-800',
        'sky' => 'bg-sky-100 text-sky-800',
        'blue' => 'bg-blue-100 text-blue-800',
        'indigo' => 'bg-indigo-100 text-indigo-800',
        'violet' => 'bg-violet-100 text-violet-800',
        'purple' => 'bg-purple-100 text-purple-800',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . ($tones[$tone] ?? $tones['slate'])]) }}>
    @if ($dot)
        <span class="badge-dot" aria-hidden="true"></span>
    @endif
    {{ $slot }}
</span>
