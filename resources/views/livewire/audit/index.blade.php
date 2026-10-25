<div>
    <x-page-header title="Journal des modifications" subtitle="Historique des changements sensibles et de leurs auteurs." icon="clock" />
    <x-filter-bar>

            <label for="audit-action" class="sr-only">Action</label>
            <select id="audit-action" wire:model.live="action" class="form-select">
                <option value="">Toutes les actions</option><option value="created">Créations</option><option value="updated">Modifications</option><option value="deleted">Suppressions</option>
            </select>

    </x-filter-bar>
    <x-data-table :headers="[
        ['label' => 'Date'], ['label' => 'Auteur'], ['label' => 'Élément'], ['label' => 'Action'], ['label' => 'Modifications'],
    ]" caption="Historique des changements sensibles">
        @foreach ($this->events as $event)
            @php
                $labels = ['Instrument' => 'Instrument', 'ChoirCommission' => 'Commission', 'FinancialType' => 'Type de contribution', 'MemberInvitation' => 'Invitation de membre', 'CommissionTeam' => 'Accès aux archives', 'User' => 'Compte', 'Document' => 'Document', 'Donation' => 'Don', 'Contribution' => 'Cotisation', 'Budget' => 'Budget', 'Transaction' => 'Opération', 'Reminder' => 'Rappel'];
                $actions = ['created' => 'Création', 'updated' => 'Modification', 'deleted' => 'Suppression'];
                $fields = ['identified_units' => 'Articles identifiés', 'unit_code' => 'Identifiant de l’article', 'unit_status' => 'Situation de l’article', 'quantity' => 'Quantité', 'member_ids'=>'Choristes (numéros de fiche)', 'name'=>'Nom de la commission', 'mission'=>'Mission', 'starts_on'=>'Début de mission', 'ends_on'=>'Fin de mission', 'archived_at'=>'Archivée le', 'type_code'=>'Type de contribution', 'label'=>'Libellé', 'default_amount'=>'Montant proposé', 'is_active'=>'Disponible', 'kind'=>'Rubrique', 'base_type'=>'Nature comptable', 'leader_id'=>'Responsable','associate_id'=>'Associé désigné','visibility'=>'Visibilité','correspondence_id'=>'Courrier lié','role'=>'Rôle','member_id'=>'Choriste','commission'=>'Commission','path'=>'Fichier','size'=>'Taille','amount'=>'Montant','donor_type'=>'Donateur','transaction_id'=>'Opération liée','donated_on'=>'Date du don','year'=>'Année','month'=>'Mois','due_amount'=>'Montant dû','paid_amount'=>'Montant réglé','paid_on'=>'Date du règlement','status'=>'Statut','category'=>'Poste','allocated_amount'=>'Alloué','committed_amount'=>'Engagé','opening_realized_amount'=>'Reprise antérieure','reference'=>'Référence','type'=>'Type','transaction_date'=>'Date','budget_id'=>'Budget lié','validated_by'=>'Validateur','attempts'=>'Tentatives'];
            @endphp
            <tr wire:key="audit-{{ $event->id }}">
                <td class="px-4 py-3 text-sm">{{ $event->created_at->translatedFormat('d/m/Y H:i:s') }}</td>
                <td class="px-4 py-3 text-sm">{{ $event->actor?->name ?? ($event->actor_id ? 'Compte supprimé' : 'Traitement automatique') }}</td>
                <td class="px-4 py-3 text-sm">{{ $labels[class_basename($event->subject_type)] ?? 'Élément' }} nº {{ $event->subject_id }}</td>
                <td class="px-4 py-3 text-sm">{{ $actions[$event->action] ?? $event->action }}</td>
                <td class="px-4 py-3 text-sm">
                    <details>
                        <summary>Voir le détail</summary>
                        <dl class="mt-2 space-y-1">
                            @foreach (array_unique(array_merge(array_keys($event->changes['before']), array_keys($event->changes['after']))) as $field)
                                <div><dt class="font-medium">{{ $fields[$field] ?? 'Valeur' }}</dt><dd class="break-all">{{ $event->changes['before'][$field] ?? '—' }} → {{ $event->changes['after'][$field] ?? '—' }}</dd></div>
                            @endforeach
                        </dl>
                    </details>
                </td>
            </tr>
        @endforeach
    </x-data-table>
    <div class="mt-4">{{ $this->events->links() }}</div>
</div>

