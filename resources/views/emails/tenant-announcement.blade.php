<x-mail::message>
# {{ $subjectLine }}

<div style="white-space: pre-line">{{ $messageBody }}</div>

@if ($actionLink && $actionLabel)
<x-mail::button :url="$actionLink">
{{ $actionLabel }}
</x-mail::button>
@endif

Bien fraternellement,  
{{ $tenantName }} · via {{ config('app.name') }}
</x-mail::message>
