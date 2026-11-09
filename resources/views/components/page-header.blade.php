@props(['title', 'subtitle' => null, 'icon' => null])

<header class="page-heading">
    <div class="min-w-0">
        <h1 class="page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="page-description">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="page-actions">{{ $actions }}</div>
    @endisset
</header>
