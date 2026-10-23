@props([
    'title',
    'subtitle' => null,
    'icon' => null,
])

<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
    <div class="flex min-w-0 items-start gap-3.5">
        @if ($icon)
            <span
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-600 ring-1 ring-inset ring-amber-100">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @endif

        <div class="min-w-0">
            <h1 class="text-balance text-xl font-semibold tracking-tight text-slate-900 sm:text-2xl">
                {{ $title }}
            </h1>
            @if ($subtitle)
                <p class="mt-1 max-w-3xl text-pretty text-sm leading-relaxed text-slate-500">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
