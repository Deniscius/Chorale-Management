@props(['value' => 0, 'compact' => false, 'currency' => true, 'class' => ''])

<span {{ $attributes->merge(['class' => 'tabular-nums '.$class]) }}>
    @if ($compact)
        {{ \App\Support\Money::compact($value, $currency) }}
    @else
        {{ \App\Support\Money::format($value, $currency) }}
    @endif
</span>
