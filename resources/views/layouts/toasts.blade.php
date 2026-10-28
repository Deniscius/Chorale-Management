@php($initialNotifications = app(\App\Support\FlashNotifications::class)->all())
<div x-data="{
        items: @js($initialNotifications),
        seed: {{ count($initialNotifications) }},
        push(detail) {
            const id = ++this.seed;
            this.items.push({
                id,
                type: detail.type ?? 'info',
                message: detail.message ?? '',
            });
            this.items = this.items.slice(-4);
        },
        remove(id) {
            this.items = this.items.filter((item) => item.id !== id);
        },
    }" @toast.window="push($event.detail)"
    class="pointer-events-none fixed inset-x-4 top-4 z-[100] flex flex-col items-end gap-2 sm:inset-x-auto sm:right-6 sm:top-6 sm:w-full sm:max-w-sm"
    aria-live="polite" aria-atomic="false">

    <template x-for="item in items" :key="item.id">
        <div data-notification-popup x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            :role="item.type === 'error' ? 'alert' : 'status'"
            class="pointer-events-auto w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl ring-1 ring-slate-900/5">
            <div class="flex items-start gap-3 p-4">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full"
                    :class="{
                        'bg-emerald-50 text-emerald-600': item.type === 'success',
                        'bg-rose-50 text-rose-600': item.type === 'error',
                        'bg-amber-50 text-amber-600': item.type === 'warning',
                        'bg-sky-50 text-sky-600': !['success', 'error', 'warning'].includes(item.type),
                    }">
                    <template x-if="item.type === 'success'">
                        <x-icon name="check-circle" class="h-5 w-5" />
                    </template>
                    <template x-if="item.type === 'error'">
                        <x-icon name="x-circle" class="h-5 w-5" />
                    </template>
                    <template x-if="item.type === 'warning'">
                        <x-icon name="warning" class="h-5 w-5" />
                    </template>
                    <template x-if="!['success', 'error', 'warning'].includes(item.type)">
                        <x-icon name="info" class="h-5 w-5" />
                    </template>
                </span>

                <div class="min-w-0 flex-1 text-sm leading-relaxed text-slate-700">
                    <p class="font-semibold" x-text="item.type === 'success' ? 'Réussi' : item.type === 'error' ? 'Erreur' : item.type === 'warning' ? 'Attention' : 'Information'"></p>
                    <p x-text="item.message"></p>
                </div>

                <button type="button" @click="remove(item.id)" aria-label="Fermer la notification"
                    class="help-control -m-1 shrink-0 rounded-lg p-1 text-slate-600 transition hover:bg-slate-100 hover:text-slate-600">
                    <x-icon name="x" class="h-4 w-4" />
                </button>
            </div>
        </div>
    </template>
</div>
