<div>
    <x-page-header title="Diagnostic des e-mails" icon="mail" subtitle="Vérifiez la configuration effective et testez la réception sur votre propre adresse." />
    <section class="space-y-3 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">Configuration chargée par l’application</h2>
        <p>Transport : {{ $transport }}. Les secrets ne sont jamais affichés.</p>
        <ul class="space-y-2">@foreach($checks as $label => $passed)<li><strong class="{{ $passed ? 'text-emerald-700' : 'text-rose-700' }}">{{ $passed ? 'OK' : 'À corriger' }}</strong> — {{ $label }}</li>@endforeach</ul>
        <p class="text-sm text-slate-600">Des paramètres enregistrés dans Render peuvent nécessiter un redéploiement. Un contrôle vert indique la présence des paramètres ; il ne vérifie ni la validité de la clé ni la validation de l’expéditeur chez Brevo.</p>
        <p>Adresse de réception de vos notifications : <strong>{{ $recipient ?: 'Non renseignée' }}</strong></p>
        <button type="button" wire:click="sendTest" wire:loading.attr="disabled" class="btn btn-primary">Envoyer un mail de test à mon adresse</button>
        @if($result)<p role="status" class="{{ $resultType === 'success' ? 'text-emerald-700' : 'text-rose-700' }}">{{ $result }}</p>@endif
    </section>
    <section class="mt-5 rounded-xl border border-slate-200 bg-white p-5">
        <h2 class="font-semibold">Derniers rappels automatiques</h2>
        <p class="mb-4 text-sm text-slate-600">La copie dans l’application et l’e-mail sont suivis séparément. « Accepté » signifie que le transport a pris le message en charge. Les notifications antérieures au suivi ne prouvent pas un envoi e-mail.</p>
        <div class="overflow-x-auto"><table class="table"><thead><tr><th>Destinataire</th><th>Type</th><th>Application</th><th>E-mail</th><th>Tentatives</th></tr></thead><tbody>
        @forelse($this->recentDispatches as $dispatch)<tr><td>{{ $dispatch->user?->name ?? 'Compte supprimé' }}</td><td>{{ $dispatch->kind }}</td><td>{{ $dispatch->notified_at?->format('d/m/Y H:i') ?? 'En attente' }}</td><td>{{ ['pending'=>'En attente de configuration ou d’envoi', 'sending'=>'En cours / à vérifier', 'accepted'=>'Accepté par le transport', 'failed'=>'Refusé — nouvelle tentative possible', 'uncertain'=>'Résultat incertain — vérifier le fournisseur', 'no_address'=>'Sans adresse valide', 'legacy'=>'Ancien envoi : statut e-mail inconnu'][$dispatch->mail_status] ?? $dispatch->mail_status }}</td><td>{{ $dispatch->attempts }}</td></tr>@empty<tr><td colspan="5">Aucun rappel automatique journalisé pour cette chorale.</td></tr>@endforelse
        </tbody></table></div>
    </section>
</div>
