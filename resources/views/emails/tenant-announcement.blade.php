<x-mail::message>
# {{ $subjectLine }}

{!! nl2br(e($messageBody)) !!}

@if ($actionLink && $actionLabel)
<x-mail::button :url="$actionLink">
{{ $actionLabel }}
</x-mail::button>
@endif

Bien fraternellement,  
{{ $tenantName }} · via {{ config('app.name') }}
</x-mail::message>
