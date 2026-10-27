@props(['text'])
<button type="button" data-speak="{{ $text }}" x-cloak x-show="available"
    class="help-control inline-flex items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-3 font-semibold text-slate-900"
    aria-label="Écouter : {{ $text }}">
    <svg aria-hidden="true" class="h-6 w-6 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M11 5 6 9H3v6h3l5 4V5Z M15 8a6 6 0 0 1 0 8 M18 5a10 10 0 0 1 0 14" />
    </svg>
    <span>Écouter</span>
</button>
