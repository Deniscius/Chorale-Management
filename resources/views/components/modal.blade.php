@props(['name', 'show' => false, 'maxWidth' => '2xl'])
<div x-data="{ show: @js($show) }"
    x-on:open-modal.window="if ($event.detail === @js($name)) show = true"
    x-on:close-modal.window="if ($event.detail === @js($name)) show = false"
    x-on:close.stop="show = false">
    <template x-if="show">
        <div>
            <x-modal-panel title="Confirmation" :size="$maxWidth" close-event="close">
                {{ $slot }}
            </x-modal-panel>
        </div>
    </template>
</div>
