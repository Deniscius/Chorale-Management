@props(['disabled' => false])

@php
    $field = $attributes->get('name') ?? $attributes->get('id');
    $invalid = $field && $errors->has($field);
@endphp

<input @disabled($disabled) @if ($invalid) aria-invalid="true" @endif
    {{ $attributes->merge(['class' => 'form-input']) }} />
