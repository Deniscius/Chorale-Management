<x-mail::message>
# Demande reçue

Bonjour {{ $firstName }},

Votre demande pour rejoindre **{{ $tenantName }}** a bien été enregistrée.

Un responsable la vérifiera. Vous recevrez un second e-mail contenant votre lien personnel d’activation lorsque votre inscription sera acceptée.

Bien fraternellement,  
{{ $tenantName }} · via {{ config('app.name') }}
</x-mail::message>
