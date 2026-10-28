<x-platform-layout title="Journal propriétaire">
    <div class="p-page-head"><div><p class="p-kicker">Traçabilité</p><h1>Journal propriétaire</h1><p class="p-description">Retrouvez les connexions réussies et les modifications d’accès à votre plateforme.</p></div><span class="p-badge p-badge-neutral">{{ $events->total() }} événement(s)</span></div>
    <section class="p-panel" aria-label="Historique des actions">
        @forelse($events as $event)
            @php($labels = ['login' => 'Connexion', 'logout' => 'Déconnexion', 'suspend' => 'Suspension', 'resume' => 'Retrait de suspension', 'extend_trial' => 'Prolongation d’essai', 'enable_free' => 'Accès gratuit accordé', 'disable_free' => 'Accès gratuit retiré', 'admin_created' => 'Création de propriétaire', 'credentials_reset' => 'Récupération du compte'])
            <article class="p-timeline-item"><span class="p-timeline-icon"><x-icon :name="in_array($event->action, ['login', 'logout', 'credentials_reset']) ? 'lock' : 'clock'" /></span><div><div class="p-timeline-title"><strong>{{ $labels[$event->action] ?? $event->action }}</strong><time datetime="{{ \Carbon\CarbonImmutable::parse($event->created_at)->toIso8601String() }}">{{ \Carbon\CarbonImmutable::parse($event->created_at)->format('d/m/Y à H:i') }}</time></div><p>{{ $event->admin_name ?? 'Opérateur serveur' }} @if($event->tenant_name)<span aria-hidden="true">·</span> {{ $event->tenant_name }}@endif</p>@if($event->reason)<p>{{ $event->reason }}</p>@endif
                @php($changes = json_decode($event->changes ?? '[]', true) ?: [])
                @if(isset($changes['before'], $changes['after']))<details><summary>Voir les changements</summary><dl class="p-detail-list" style="margin-top:13px">@foreach(['suspended_at' => 'Suspension', 'trial_ends_at' => 'Fin de l’essai'] as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ filled($changes['before'][$key] ?? null) ? \Carbon\CarbonImmutable::parse($changes['before'][$key])->format('d/m/Y H:i') : 'Aucune' }} → {{ filled($changes['after'][$key] ?? null) ? \Carbon\CarbonImmutable::parse($changes['after'][$key])->format('d/m/Y H:i') : 'Aucune' }}</dd></div>@endforeach</dl></details>@endif
            </div></article>
        @empty<div class="p-empty"><x-icon name="clock" /><strong>Aucun événement pour le moment</strong><p>Les prochaines connexions et modifications apparaîtront ici.</p></div>@endforelse
        @if($events->hasPages())<div class="p-pagination">{{ $events->links() }}</div>@endif
    </section>
</x-platform-layout>
