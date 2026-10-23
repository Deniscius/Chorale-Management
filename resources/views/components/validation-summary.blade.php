@if ($errors->any())
    <div data-validation-summary tabindex="-1" role="alert" aria-live="polite" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        <p class="font-semibold">L'enregistrement n'a pas abouti. Vérifiez les champs suivants :</p>
        <ul class="mt-2 list-inside list-disc">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
