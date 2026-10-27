@props(['number', 'title', 'icon', 'help'])
<fieldset class="min-w-0 rounded-2xl border border-slate-200 p-4">
    <legend class="flex max-w-full items-center gap-2 px-2 text-base font-bold text-slate-900">
        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-900">{{ $number }}</span>
        <x-icon :name="$icon" class="h-6 w-6 shrink-0 text-indigo-700" />
        <span>{{ $title }}</span>
    </legend>
    <div class="mb-4 flex flex-wrap items-start gap-3">
        <p class="min-w-0 flex-1 basis-52 text-sm leading-relaxed text-slate-700">{{ $help }}</p>
        <div class="flex flex-wrap gap-2">
            <x-listen-button :text="$help" />
            <button type="button" x-cloak x-show="speaking" @click="stop()" aria-label="Arrêter la lecture"
                class="help-control inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-3 font-semibold text-slate-900">
                <span aria-hidden="true">■</span> Arrêter
            </button>
        </div>
    </div>
    <div class="space-y-4">{{ $slot }}</div>
</fieldset>
