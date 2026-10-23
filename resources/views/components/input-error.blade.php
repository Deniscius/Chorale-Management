@props(['messages' => null])

@if ($messages)
    <div {{ $attributes->merge(['class' => 'form-error']) }} role="alert">
        <x-icon name="warning" class="mt-px h-3.5 w-3.5 shrink-0" />
        <div class="space-y-0.5">
            @foreach ((array) $messages as $message)
                <p>{{ $message }}</p>
            @endforeach
        </div>
    </div>
@endif
