@props(['icon' => 'inbox', 'title' => 'Aucun résultat', 'message' => null, 'compact' => false, 'border' => true])
<div @class(['empty-state', 'work-surface' => $border, 'py-8' => $compact, 'py-14' => !$compact])>
    <h3 class="empty-state-title">{{ $title }}</h3>
    @if($message)<p class="mt-2 max-w-prose text-sm leading-relaxed">{{ $message }}</p>@endif
    @isset($actions)<div class="mt-5 flex flex-wrap items-center gap-2">{{ $actions }}</div>@endisset
</div>
