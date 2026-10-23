{{-- Logo de la chorale : image téléversée si disponible, sinon icône musicale. --}}
@props([
    'iconClass' => 'h-5 w-5',
])

@php
    $identity = app(\App\Services\ChoraleIdentity::class);
    $logoUrl = $identity->logoUrl();
@endphp

@if ($logoUrl)
    <img src="{{ $logoUrl }}" alt="Logo {{ $identity->name() }}"
        {{ $attributes->merge(['class' => 'h-full w-full object-contain']) }} />
@else
    <x-icon name="music" :class="$iconClass" />
@endif
