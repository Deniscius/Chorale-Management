@props(['title' => null])

<div {{ $attributes->merge(['class' => 'card p-4 sm:p-5']) }}>
    @if ($title)
        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $title }}</p>
    @endif

    {{ $slot }}
</div>
