<div>
    <x-page-header title="Avances des choristes" icon="banknotes" subtitle="Dépenses payées personnellement, décisions de remboursement et suivi des paiements.">
        <x-slot name="actions">
            <a href="{{ route('transactions.index', ['activite' => $activityId]) }}" wire:navigate class="btn btn-secondary">Journal comptable</a>
            @if($this->canManage())<button type="button" wire:click="create" class="btn btn-primary">Enregistrer une avance</button>@endif
        </x-slot>
    </x-page-header>
    <p class="mb-4 text-sm text-slate-600">Enregistrez la dépense avancée par un choriste, puis la décision du bureau et les remboursements réellement effectués. Chaque paiement crée une seule dépense dans le journal ; l’avance reste présentée séparément.</p>
    <x-filter-bar>
        <div class="grid gap-4 md:grid-cols-2">
            <div><label for="expense-filter-activity" class="form-label">Activité</label><select id="expense-filter-activity" wire:model.live="activityId" class="form-select"><option value="">Toutes les activités</option>@foreach($this->activities as $activity)<option value="{{ $activity->id }}">{{ $activity->title }}</option>@endforeach</select></div>
            <div><label for="expense-filter-status" class="form-label">Décision</label><select id="expense-filter-status" wire:model.live="statusFilter" class="form-select"><option value="">Toutes les décisions</option>@foreach($statuses as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
        </div>
    </x-filter-bar>
    <div class="mt-4">
        <x-data-table :headers="[['label'=>'Date / dépense'], ['label'=>'Choriste / activité'], ['label'=>'Avancé'], ['label'=>'Remboursé'], ['label'=>'Reste dû'], ['label'=>'Décision / réclamation'], ['label'=>'Actions']]" empty-title="Aucune avance" empty-message="Aucune dépense avancée ne correspond à la sélection.">
            @foreach($this->expenses as $expense)
                <tr wire:key="expense-{{ $expense->id }}">
                    <td class="px-4 py-3">{{ $expense->incurred_on->format('d/m/Y') }}<p class="font-medium">#{{ $expense->id }} — {{ $expense->description }}</p></td>
                    <td class="px-4 py-3">{{ $expense->member->full_name }}<p class="text-xs text-slate-600">{{ $expense->activity?->title ?? 'Hors activité' }}</p></td>
                    <td class="px-4 py-3">{{ number_format((float) $expense->amount, 2, ',', ' ') }}</td>
                    <td class="px-4 py-3">{{ number_format($expense->repaidAmount(), 2, ',', ' ') }}</td>
                    <td class="px-4 py-3">{{ $expense->status === 'approved' ? number_format($expense->outstandingAmount(), 2, ',', ' ').' FCFA' : '—' }}</td>
                    <td class="px-4 py-3">{{ $statuses[$expense->status] }}<p class="text-xs text-slate-600">{{ $expense->claimed_on ? 'Réclamé le '.$expense->claimed_on->format('d/m/Y') : 'Sans réclamation enregistrée' }}</p>@if($expense->status === 'approved' && $expense->outstandingAmount() === 0.0)<p class="text-emerald-700">Remboursé intégralement</p>@endif</td>
                    <td class="px-4 py-3">@if($this->canManage())<button type="button" wire:click="selectExpense({{ $expense->id }})" class="btn btn-secondary btn-sm">Détail / traiter</button>@endif</td>
                </tr>
            @endforeach
        </x-data-table>
    </div>
    <div class="mt-4">{{ $this->expenses->links() }}</div>
    @if($showForm)
        <section x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })" class="mt-5 rounded-xl border border-slate-200 bg-white p-5" aria-label="Nouvelle avance">
            <h2 class="mb-4 font-semibold">Enregistrer une dépense payée par un choriste</h2>
            <form wire:submit="save" class="space-y-4">
                <x-validation-summary />
                <div class="grid gap-4 md:grid-cols-2">
                    <div><x-input id="expense-member-search" name="memberSearch" label="Rechercher un choriste" wire:model.live.debounce.300ms="memberSearch" /><label for="expense-member" class="form-label">Choriste ayant payé</label><select id="expense-member" wire:model="member_id" class="form-select" required><option value="">Choisir…</option>@foreach($this->members as $member)<option value="{{ $member->id }}">{{ $member->full_name }}</option>@endforeach</select></div>
                    <div><label for="expense-activity" class="form-label">Activité liée</label><select id="expense-activity" wire:model="activity_id" class="form-select"><option value="">Hors activité</option>@foreach($this->activities as $activity)<option value="{{ $activity->id }}">{{ $activity->title }}</option>@endforeach</select></div>
                    <x-input id="expense-date" name="incurred_on" label="Date de la dépense" type="date" wire:model="incurred_on" required />
                    <x-input id="expense-amount" name="amount" label="Montant avancé (FCFA)" type="number" step="0.01" min="0.01" wire:model="amount" required />
                    <x-input id="expense-description" name="description" label="Objet de la dépense" wire:model="description" required />
                    <x-input id="expense-evidence" name="evidence_reference" label="Référence du justificatif" wire:model="evidence_reference" />
                    <x-input id="expense-claimed" name="claimed_on" label="Date de réclamation du choriste (facultatif)" type="date" wire:model="claimed_on" />
                    <x-input id="expense-notes" name="notes" label="Notes" wire:model="notes" />
                </div>
                <div class="flex gap-3"><button type="submit" wire:loading.attr="disabled" class="btn btn-primary">Enregistrer l’avance</button><button type="button" wire:click="$set('showForm', false)" class="btn btn-secondary">Annuler</button></div>
            </form>
        </section>
    @endif
    @if($this->selectedExpense)
        @php($selected = $this->selectedExpense)
        <section wire:key="expense-detail-{{ $selected->id }}" x-init="$el.scrollIntoView({ behavior: 'smooth', block: 'start' })" class="mt-5 space-y-4 rounded-xl border border-slate-200 bg-white p-5" aria-label="Détail de l’avance">
            <h2 class="font-semibold">Avance #{{ $selected->id }} — {{ $selected->member->full_name }}</h2>
            <p>{{ $selected->description }} · {{ $selected->activity?->title ?? 'Hors activité' }} · {{ number_format((float) $selected->amount, 2, ',', ' ') }} FCFA</p>
            <p>Justificatif : {{ $selected->evidence_reference ?: 'Non renseigné' }} · {{ $selected->notes }}</p>
            <p>{{ $selected->decision_note }} @if($selected->decided_at) — Décision enregistrée par {{ $selected->decisionMaker?->name ?? 'Compte supprimé' }} le {{ $selected->decided_at->format('d/m/Y à H:i') }}@endif</p>
            <x-validation-summary />
            @if(!$selected->claimed_on && $selected->repaidAmount() < (float) $selected->amount)<button type="button" wire:click="recordClaim({{ $selected->id }})" class="btn btn-secondary">Enregistrer une réclamation aujourd’hui</button>@endif
            @if($selected->repaidAmount() === 0.0)
                <form wire:submit="decide" class="space-y-3 border-t pt-4">
                    <h3 class="font-semibold">Décision du bureau</h3>
                    <label for="expense-decision" class="form-label">Décision</label><select id="expense-decision" wire:model="decision" class="form-select"><option value="approved">Approuver le remboursement</option><option value="rejected">Refuser le remboursement</option></select>
                    <x-input id="expense-decision-note" name="decision_note" label="Motif / référence de la décision du bureau" wire:model="decision_note" required />
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-secondary">Enregistrer la décision</button>
                </form>
            @endif
            @if($selected->status === 'approved' && $selected->outstandingAmount() > 0)
                <form wire:submit="reimburse" class="space-y-3 border-t pt-4">
                    <h3 class="font-semibold">Enregistrer un remboursement effectué</h3>
                    <p>Reste à rembourser : {{ number_format($selected->outstandingAmount(), 2, ',', ' ') }} FCFA. Un paiement partiel est possible.</p>
                    <div class="grid gap-4 md:grid-cols-2">
                        <x-input id="repayment-amount" name="amount" label="Montant remboursé (FCFA)" type="number" step="0.01" min="0.01" wire:model="paymentAmount" required />
                        <x-input id="repayment-date" name="transaction_date" label="Date du paiement" type="date" wire:model="paymentDate" required />
                        <div><label for="repayment-account" class="form-label">Compte d’opération</label><select id="repayment-account" wire:model="account_id" class="form-select" required><option value="">Choisir…</option>@foreach($this->accounts as $account)<option value="{{ $account->id }}">{{ $account->code }} — {{ $account->name }}</option>@endforeach</select><a href="{{ route('accounting.accounts') }}" wire:navigate class="text-sm underline">Gérer les comptes</a></div>
                        <div><label for="repayment-method" class="form-label">Mode de paiement</label><select id="repayment-method" wire:model="payment_method" class="form-select">@foreach($paymentMethods as $value=>$label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                    </div>
                    <x-input id="repayment-notes" name="notes" label="Référence du paiement / notes" wire:model="paymentNotes" />
                    <p class="text-sm text-slate-600">Cette saisie enregistre un paiement déjà réalisé. Elle ne déclenche aucun transfert bancaire.</p>
                    <button type="submit" wire:loading.attr="disabled" class="btn btn-primary">Enregistrer le paiement</button>
                </form>
            @endif
            <h3 class="font-semibold">Historique des remboursements</h3>
            <ul class="space-y-2">@forelse($selected->repayments as $repayment)<li>{{ $repayment->transaction_date->format('d/m/Y') }} · {{ $repayment->reference }} · {{ number_format((float) $repayment->amount, 2, ',', ' ') }} FCFA · {{ $repayment->account?->name ?? 'Compte non renseigné' }} · {{ $repayment->notes }}</li>@empty<li>Aucun remboursement effectué.</li>@endforelse</ul>
            <button type="button" wire:click="closeDetail" class="btn btn-secondary">Fermer le détail</button>
        </section>
    @endif
</div>
