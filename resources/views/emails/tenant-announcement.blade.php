<x-mail::message>
# {{ $subjectLine }}

{!! nl2br(e($messageBody)) !!}

@if ($actionUrl && $actionLabel)
<x-mail::button :url="$actionUrl">
{{ $actionLabel }}
</x-mail::button>
@endif

Bien fraternellement,  
{{ config('app.name') }}
</x-mail::message>
