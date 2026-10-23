<a role="menuitem"
    {{ $attributes->merge(['class' => 'flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-start text-sm text-slate-700 transition hover:bg-slate-100 hover:text-slate-900 focus:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-slate-400']) }}>
    @isset($icon)
        {{ $icon }}
    @endisset
    {{ $slot }}
</a>
