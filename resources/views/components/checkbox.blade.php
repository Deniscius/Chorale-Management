@props([
    'name',
    'label',
    'error' => null,
    'help' => null,
    'description' => null,
])

@php
    $id = $attributes->get('id') ?? $name;
    $message = $error ?? ($name ? $errors->first($name) : null);
    $helpId = $help ? $id . '-help' : null;
    $errorId = $message ? $id . '-error' : null;
    $describedBy = implode(' ', array_filter([$helpId, $errorId]));
@endphp

<div>
    <label for="{{ $id }}" class="flex items-start gap-2.5">
        <input id="{{ $id }}" name="{{ $name }}" type="checkbox"
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except(['id', 'name'])->merge(['class' => 'form-checkbox mt-0.5']) }} />

        <span class="min-w-0">
            <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>
            @if ($description)
                <span class="mt-0.5 block text-xs text-slate-500">{{ $description }}</span>
            @endif
        </span>
    </label>

    @if ($help)
        <p id="{{ $helpId }}" class="form-help">{{ $help }}</p>
    @endif

    @if ($message)
        <p id="{{ $errorId }}" class="form-error" role="alert">
            <x-icon name="warning" class="mt-px h-3.5 w-3.5 shrink-0" />
            <span>{{ $message }}</span>
        </p>
    @endif
</div>
