<x-mail::message>
# Demande reçue

Bonjour {{ $firstName }},

Votre demande pour rejoindre **{{ $tenantName }}** a bien été enregistrée. Confirmez d’abord cette adresse e-mail :

<x-mail::button :url="$verificationUrl">
Confirmer mon adresse e-mail
</x-mail::button>

Ce lien expire dans 24 heures. Après confirmation, un responsable vérifiera votre demande puis vous recevrez votre lien personnel d’activation.

Bien fraternellement,  
{{ $tenantName }} · via {{ config('app.name') }}
</x-mail::message>
