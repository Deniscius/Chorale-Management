<div>
    <x-page-header title="Activité des utilisateurs" subtitle="Connexions, déconnexions et modifications tracées pour cette chorale." icon="clock" />
    <section class="work-surface mb-5 p-4" aria-label="Dernières connexions">
        <h2 class="mb-3 text-sm font-semibold text-slate-800">Dernières connexions réussies</h2>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
            @forelse($this->recentLogins as $login)
                <div wire:key="login-{{ $login->id }}" class="rounded-lg border border-slate-100 bg-slate-50 px-3 py-2">
                    <p class="truncate text-sm font-medium text-slate-800">{{ $login->actor?->name ?? 'Compte supprimé' }}</p>
                    <p class="text-xs text-slate-600">{{ $login->created_at->format('d/m/Y à H:i') }}</p>
                </div>
            @empty
                <p class="text-sm text-slate-500">Aucune connexion enregistrée pour cette chorale.</p>
            @endforelse
        </div>
    </section>
    <x-filter-bar title="Retrouver un événement">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="lg:col-span-2">
                <label for="audit-search" class="form-label">Rechercher un utilisateur ou un numéro d'élément</label>
                <input id="audit-search" type="search" wire:model.live.debounce.350ms="search" maxlength="100" class="form-input mt-1" placeholder="Nom, identifiant ou n° de fiche">
            </div>
            <div>
                <label for="audit-actor" class="form-label">Utilisateur</label>
                <select id="audit-actor" wire:model.live="actorId" class="form-select mt-1">
                    <option value="">Tous les utilisateurs</option>
                    @foreach($this->users as $user)<option value="{{ $user->id }}">{{ $user->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label for="audit-action" class="form-label">Action</label>
                <select id="audit-action" wire:model.live="action" class="form-select mt-1">
                    <option value="">Toutes les actions</option>
                    <option value="login">Connexion</option><option value="logout">Déconnexion</option>
                    <option value="created">Création</option><option value="updated">Modification</option><option value="deleted">Suppression</option>
                </select>
            </div>
            <div class="flex items-end">
                <button type="button" wire:click="resetFilters" class="btn btn-secondary w-full">Effacer les filtres</button>
            </div>
            <div><label for="audit-from" class="form-label">Depuis le</label><input type="date" id="audit-from" wire:model.live="from" class="form-input mt-1"></div>
            <div><label for="audit-until" class="form-label">Jusqu'au</label><input type="date" id="audit-until" wire:model.live="until" class="form-input mt-1"></div>
        </div>
        @error('until') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </x-filter-bar>
    <x-data-table :headers="[
        ['label' => 'Date'], ['label' => 'Auteur'], ['label' => 'Élément'], ['label' => 'Action'], ['label' => 'Modifications'],
    ]" caption="Historique des changements sensibles">
        @foreach ($this->events as $event)
            @php
                $labels = ['RehearsalRhythm' => 'Rythme de répétition', 'Instrument' => 'Instrument', 'ChoirCommission' => 'Commission', 'FinancialType' => 'Type de contribution', 'MemberInvitation' => 'Invitation de membre', 'CommissionTeam' => 'Accès aux archives', 'User' => 'Compte', 'Document' => 'Document', 'Donation' => 'Don', 'Contribution' => 'Cotisation', 'Budget' => 'Budget', 'Transaction' => 'Opération', 'Reminder' => 'Rappel'];
                $actions = ['created' => 'Création', 'updated' => 'Modification', 'deleted' => 'Suppression', 'login' => 'Connexion', 'logout' => 'Déconnexion'];
                $fields = ['identified_units' => 'Articles identifiés', 'unit_code' => 'Identifiant de l’article', 'unit_status' => 'Situation de l’article', 'quantity' => 'Quantité', 'member_ids'=>'Choristes (numéros de fiche)', 'name'=>'Nom de la commission', 'mission'=>'Mission', 'starts_on'=>'Début de mission', 'ends_on'=>'Fin de mission', 'archived_at'=>'Archivée le', 'weekday'=>'Jour de la semaine','starts_at'=>'Heure de début','ends_at'=>'Heure de fin','venue'=>'Lieu','effective_from'=>'Applicable à partir du','effective_until'=>'Applicable jusqu’au','choir_master_ids'=>'Maîtres de chœur', 'type_code'=>'Type de contribution', 'label'=>'Libellé', 'default_amount'=>'Montant proposé', 'is_active'=>'Disponible', 'kind'=>'Rubrique', 'base_type'=>'Nature comptable', 'leader_id'=>'Responsable','associate_id'=>'Associé désigné','visibility'=>'Visibilité','correspondence_id'=>'Courrier lié','role'=>'Rôle','member_id'=>'Choriste','commission'=>'Commission','path'=>'Fichier','size'=>'Taille','amount'=>'Montant','donor_type'=>'Donateur','transaction_id'=>'Opération liée','donated_on'=>'Date du don','year'=>'Année','month'=>'Mois','due_amount'=>'Montant dû','paid_amount'=>'Montant réglé','paid_on'=>'Date du règlement','status'=>'Statut','category'=>'Poste','allocated_amount'=>'Alloué','committed_amount'=>'Engagé','opening_realized_amount'=>'Reprise antérieure','reference'=>'Référence','type'=>'Type','transaction_date'=>'Date','budget_id'=>'Budget lié','validated_by'=>'Validateur','attempts'=>'Tentatives'];
                $changeSet = $event->changeSet();
                $changedFields = array_unique(array_merge(array_keys($changeSet['before']), array_keys($changeSet['after'])));
            @endphp
            <tr wire:key="audit-{{ $event->id }}">
                <td class="px-4 py-3 text-sm">{{ $event->created_at->translatedFormat('d/m/Y H:i:s') }}</td>
                <td class="px-4 py-3 text-sm">{{ $event->actor?->name ?? ($event->actor_id ? 'Compte supprimé' : 'Traitement automatique') }}</td>
                <td class="px-4 py-3 text-sm">{{ in_array($event->action, ['login', 'logout'], true) ? 'Session' : ($labels[class_basename($event->subject_type)] ?? 'Élément') }} @unless(in_array($event->action, ['login', 'logout'], true)) nº {{ $event->subject_id }} @endunless</td>
                <td class="px-4 py-3 text-sm">{{ $actions[$event->action] ?? $event->action }}</td>
                <td class="px-4 py-3 text-sm">
                    @if($changedFields !== [])
                    <details>
                        <summary>Voir le détail</summary>
                        <dl class="mt-2 space-y-1">
                            @foreach ($changedFields as $field)
                                <div><dt class="font-medium">{{ $fields[$field] ?? $field }}</dt><dd class="break-all">{{ \App\Models\AuditEvent::displayValue($changeSet['before'][$field] ?? null) }} → {{ \App\Models\AuditEvent::displayValue($changeSet['after'][$field] ?? null) }}</dd></div>
                            @endforeach
                        </dl>
                    </details>
                    @else
                        <span class="text-slate-500">{{ in_array($event->action, ['login', 'logout'], true) ? 'Événement de session (aucun secret enregistré).' : 'Détail indisponible pour cet ancien événement.' }}</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>
    <div class="mt-4">{{ $this->events->links() }}</div>
</div>

