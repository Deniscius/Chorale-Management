@props(['enum' => null, 'label' => null, 'class' => ''])

@php
    $text = $label ?? (is_object($enum) && method_exists($enum, 'label') ? $enum->label() : (string) $enum);
    $color = is_object($enum) && method_exists($enum, 'color') ? $enum->color() : 'slate';
@endphp

<span {{ $attributes->merge(['class' => "badge whitespace-nowrap bg-{$color}-100 text-{$color}-800 {$class}"]) }}>
    {{ $text }}
</span>
