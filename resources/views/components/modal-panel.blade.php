@props([
    'title' => null,
    'subtitle' => null,
    'size' => 'xl',
])

@php
    $id = 'modal-title-' . \Illuminate\Support\Str::random(8);
    $sizes = [
        'sm' => 'max-w-md',
        'md' => 'max-w-lg',
        'lg' => 'max-w-2xl',
        'xl' => 'max-w-3xl',
        '2xl' => 'max-w-4xl',
        '3xl' => 'max-w-5xl',
    ];
@endphp

<div x-data="{
        returnFocus: null,
        previousOverflow: '',
        init() {
            this.returnFocus = document.activeElement;
            this.previousOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                const target = this.$el.querySelector('[autofocus]') || this.focusables()[0];
                target?.focus();
            });
        },
        destroy() {
            document.body.style.overflow = this.previousOverflow || '';
            if (this.returnFocus && this.returnFocus.isConnected) {
                this.returnFocus.focus();
            }
        },
        focusables() {
            const selector = 'a[href], button:not([disabled]), input:not([disabled]):not([type=hidden]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex=\'-1\'])';
            return Array.from(this.$el.querySelectorAll(selector))
                .filter((el) => el.offsetParent !== null || el === document.activeElement);
        },
        trapTab(event) {
            const items = this.focusables();
            if (items.length === 0) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
     }"
    x-on:keydown.escape.window="$dispatch('close-form')" x-on:keydown.tab="trapTab($event)"
    class="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}">

    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm animate-fade-in" wire:click="$dispatch('close-form')"
        aria-hidden="true"></div>

    <div class="relative flex min-h-full items-start justify-center px-4 py-6 sm:px-6 sm:py-10">
        <div
            class="relative flex max-h-[calc(100dvh-3rem)] w-full {{ $sizes[$size] ?? $sizes['xl'] }} animate-modal-pop flex-col overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-slate-900/5">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                <div class="min-w-0">
                    <h2 id="{{ $id }}" class="text-base font-semibold text-slate-900 sm:text-lg">
                        {{ $title }}
                    </h2>
                    @if ($subtitle)
                        <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                    @endif
                </div>

                <button type="button" wire:click="$dispatch('close-form')" aria-label="Fermer la fenêtre"
                    class="-m-1.5 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
                    <x-icon name="x" class="h-5 w-5" />
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-6 py-6">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
