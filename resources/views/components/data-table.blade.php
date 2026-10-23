@props([
    'headers' => [],
    'caption' => null,
    'emptyTitle' => 'Aucun résultat',
    'emptyMessage' => null,
    'emptyIcon' => 'inbox',
    'loading' => true,
])

<div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
    <div class="relative">
        @if ($loading)
            <div wire:loading class="absolute inset-x-0 top-0 z-20 h-0.5 animate-pulse bg-slate-900/70"
                aria-hidden="true"></div>
        @endif

        <div class="overflow-x-auto scrollbar-slim">
            <table class="table transition-opacity duration-150" wire:loading.delay.class="opacity-60"
                wire:loading.delay.attr="aria-busy">
                @if ($caption)
                    <caption class="sr-only">{{ $caption }}</caption>
                @endif

                <thead>
                    <tr>
                        @foreach ($headers as $header)
                            <th scope="col"
                                class="{{ $header['align'] ?? 'text-left' }} {{ $header['class'] ?? '' }}">
                                {{ $header['label'] }}
                                @if ($header['hint'] ?? false)
                                    <span
                                        class="ml-1 font-normal normal-case tracking-normal text-slate-400">{{ $header['hint'] }}</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody>
                    @if ($slot->isEmpty())
                        <tr>
                            <td colspan="{{ max(count($headers), 1) }}" class="table-empty">
                                <x-empty-state :icon="$emptyIcon" :title="$emptyTitle" :message="$emptyMessage"
                                    :border="false" compact />
                            </td>
                        </tr>
                    @else
                        {{ $slot }}
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>
