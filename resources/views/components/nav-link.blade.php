<a
    {{ $attributes->merge(['class' => 'group flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-400/70 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900']) }}>
    @isset($icon)
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-800/60 text-slate-300 group-hover:text-white">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endisset
    <span class="min-w-0">{{ $slot }}</span>
</a>
