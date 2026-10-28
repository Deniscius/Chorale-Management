@props(['tenant'])
@php
    $kind = match (true) {
        $tenant->suspended_at !== null => 'suspended',
        $tenant->paid_perpetual || $tenant->paid_until?->isFuture() => 'paid',
        $tenant->isTrialing() => 'trial',
        default => 'expired',
    };
@endphp
<span class="p-badge p-badge-{{ $kind }}">{{ $tenant->accessLabel() }}</span>
