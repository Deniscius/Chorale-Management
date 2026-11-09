@props(['label', 'value', 'icon' => 'chart', 'hint' => null, 'color' => 'blue', 'href' => null])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" wire:navigate @endif class="metric">
    <p class="metric-label">{{ $label }}</p>
    <p class="metric-value">{{ $value }}</p>
    @if($hint)<p class="metric-hint">{{ $hint }}</p>@endif
</{{ $tag }}>
