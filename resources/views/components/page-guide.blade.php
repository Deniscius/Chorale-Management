@php
    $guide = collect(config('accessibility.guides', []))
        ->first(fn ($value, $pattern) => request()->routeIs($pattern));
@endphp
@if ($guide)
    <details class="mt-1">
        <summary class="help-control cursor-pointer rounded-lg px-2 py-3 font-semibold">Pas à pas : {{ $guide['title'] }}</summary>
        <ol class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach ($guide['steps'] as $step)
                <li class="border-l-2 border-slate-200 p-4">
                    <div class="flex items-start gap-3">
                        <x-icon :name="$step['icon']" class="h-5 w-5 shrink-0 text-choir-700" />
                        <p class="text-base text-slate-800"><span class="font-bold">{{ $loop->iteration }}.</span> {{ $step['text'] }}</p>
                    </div>
                    <div class="mt-3"><x-listen-button :text="$step['text']" /></div>
                </li>
            @endforeach
        </ol>
    </details>
@endif
