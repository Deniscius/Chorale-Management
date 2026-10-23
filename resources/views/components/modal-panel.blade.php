@props(['title' => null, 'subtitle' => null, 'size' => 'xl', 'closeEvent' => 'close-form'])
@php
    $sizes = ['sm' => 'max-w-md', 'md' => 'max-w-lg', 'lg' => 'max-w-2xl', 'xl' => 'max-w-3xl', '2xl' => 'max-w-4xl', '3xl' => 'max-w-5xl'];
@endphp
<div x-data="modalPanel(@js($closeEvent))" x-id="['modal-title', 'modal-description']"
    x-on:input="markDirty($event)" x-on:change="markDirty($event)" x-on:click.capture="guardClose($event)"
    x-on:beforeunload.window="beforeUnload($event)" x-on:keydown.escape.window="escape($event)" x-on:keydown.tab="trapTab($event)"
    x-on:livewire-upload-start="uploads++" x-on:livewire-upload-finish="uploads = Math.max(0, uploads - 1)"
    x-on:livewire-upload-error="uploads = Math.max(0, uploads - 1)" x-on:livewire-upload-cancel="uploads = Math.max(0, uploads - 1)"
    class="fixed inset-0 z-[70] overflow-y-auto" role="dialog" aria-modal="true" tabindex="-1"
    :aria-labelledby="$id('modal-title')" @if($subtitle) :aria-describedby="$id('modal-description')" @endif :aria-busy="busy">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm animate-fade-in" aria-hidden="true"></div>
    <div class="relative flex min-h-full items-start justify-center px-3 py-3 sm:px-6 sm:py-8" x-on:click.self="requestClose()">
        <div class="relative flex max-h-[calc(100dvh-1.5rem)] w-full {{ $sizes[$size] ?? $sizes['xl'] }} animate-modal-pop flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5 sm:max-h-[calc(100dvh-4rem)]">
            <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-100 px-4 py-4 sm:px-6">
                <div class="min-w-0">
                    <h2 :id="$id('modal-title')" class="text-base font-semibold text-slate-900 sm:text-lg">{{ $title }}</h2>
                    @if ($subtitle)<p :id="$id('modal-description')" class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>@endif
                </div>
                <button type="button" x-on:click="requestClose()" :disabled="busy" aria-label="Fermer la fenêtre"
                    class="-m-1.5 rounded-lg p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 disabled:opacity-40">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-5 sm:px-6">{{ $slot }}</div>
            <div x-cloak x-show="busy" class="shrink-0 border-t bg-slate-50 px-4 py-2 text-sm text-slate-600" role="status">Traitement en cours…</div>
            <div x-cloak x-show="confirming" class="shrink-0 border-t border-amber-200 bg-amber-50 px-4 py-4 sm:px-6" role="alert">
                <p class="text-sm font-medium text-amber-950">Vos modifications ne sont pas enregistrées. Voulez-vous les abandonner ?</p>
                <div class="mt-3 flex flex-wrap justify-end gap-2">
                    <button type="button" x-ref="keepEditing" x-on:click="keepEditing()" class="btn btn-secondary">Continuer la saisie</button>
                    <button type="button" x-on:click="discard()" :disabled="busy" class="btn btn-primary">Abandonner les modifications</button>
                </div>
            </div>
        </div>
    </div>
</div>
