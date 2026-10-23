<x-mail::message>
# Rappel de répétition

Bonjour{{ $member?->first_name ? ' '.$member->first_name : '' }},

{{ $body }}

@if ($rehearsal->venue)
**Lieu :** {{ $rehearsal->venue }}
@endif

@if ($rehearsal->timeRange())
**Horaire :** {{ $rehearsal->timeRange() }}
@endif

@if ($rehearsal->works->isNotEmpty())
**Œuvres au programme :**

@foreach ($rehearsal->works as $work)
- {{ $work->title }}@if ($work->composer) ({{ $work->composer }})@endif
@endforeach
@endif

Merci de votre présence et de votre ponctualité.

Bien fraternellement,  
La chorale
</x-mail::message>
