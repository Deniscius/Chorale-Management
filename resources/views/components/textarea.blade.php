@props([
    'name',
    'label' => null,
    'rows' => 3,
    'error' => null,
    'help' => null,
    'required' => false,
    'hideLabel' => false,
    'class' => '',
])

@php
    $id = $attributes->get('id') ?? $name;
    $message = $error ?? ($name ? $errors->first($name) : null);
    $helpId = $help ? $id . '-help' : null;
    $errorId = $message ? $id . '-error' : null;
    $describedBy = implode(' ', array_filter([$helpId, $errorId]));
@endphp

<div class="{{ $class }}">
    @if ($label)
        <label for="{{ $id }}" @class(['form-label', 'sr-only' => $hideLabel])>
            {{ $label }}@if ($required)<span class="ml-0.5 text-rose-500" aria-hidden="true">*</span>@endif
        </label>
    @endif

    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @if ($required) required @endif
        @if ($message) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{ $attributes->except(['id', 'name'])->merge(['class' => 'form-textarea']) }}>{{ $slot }}</textarea>

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
