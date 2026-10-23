@props([
    'icon' => 'inbox',
    'title' => 'Aucun résultat',
    'message' => null,
    'compact' => false,
    'border' => true,
])

<div @class([
    'flex flex-col items-center justify-center px-6 text-center',
    'rounded-xl border border-dashed border-slate-300 bg-white' => $border,
    'py-8' => $compact,
    'py-14' => !$compact,
])>
    <span
        class="flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-slate-400 ring-1 ring-inset ring-slate-200">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>

    <h3 class="mt-4 text-sm font-semibold text-slate-900">{{ $title }}</h3>

    @if ($message)
        <p class="mt-1 max-w-sm text-sm leading-relaxed text-slate-500">{{ $message }}</p>
    @endif

    @isset($actions)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">{{ $actions }}</div>
    @endisset
</div>
