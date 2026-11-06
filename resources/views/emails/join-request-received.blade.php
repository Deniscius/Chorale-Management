<x-mail::message>
# Demande reçue

Bonjour {{ $firstName }},

Votre demande pour rejoindre **{{ $tenantName }}** a bien été enregistrée. Un responsable va l’examiner.

Lorsque la demande sera acceptée, vous recevrez ici un lien personnel pour choisir votre identifiant et votre mot de passe. L’ouverture de ce lien confirmera que cette adresse e-mail vous appartient.

Bien fraternellement,  
{{ $tenantName }} · via {{ config('app.name') }}
</x-mail::message>
