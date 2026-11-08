<section aria-label="Avances des choristes">
    <h3>Avances des choristes et remboursements</h3>
    <p>Situation à la date de génération du rapport. Sommes payées personnellement par les choristes. Les remboursements effectués figurent déjà dans les écritures de dépenses et ne sont pas additionnés une seconde fois.</p>
    <p>Avances enregistrées : {{ number_format($memberExpenses->sum('amount'), 2, ',', ' ') }} FCFA · Remboursé : {{ number_format($memberExpenses->sum(fn ($expense) => $expense->repaidAmount()), 2, ',', ' ') }} FCFA · Reste approuvé à rembourser : {{ number_format($memberExpenses->sum(fn ($expense) => $expense->outstandingAmount()), 2, ',', ' ') }} FCFA.</p>
    <div class="table-scroll"><table>
        <thead><tr><th>Date / référence</th><th>Choriste / dépense</th><th>Avancé</th><th>Remboursé</th><th>Reste dû</th><th>Décision / réclamation</th></tr></thead>
        <tbody>
            @forelse($memberExpenses as $expense)
                <tr><td>{{ $expense->incurred_on->format('d/m/Y') }} · #{{ $expense->id }}</td><td>{{ $expense->member->full_name }}<br>{{ $expense->description }}<br>{{ $expense->evidence_reference }}</td><td>{{ number_format((float) $expense->amount, 2, ',', ' ') }}</td><td>{{ number_format($expense->repaidAmount(), 2, ',', ' ') }}</td><td>{{ $expense->status === 'approved' ? number_format($expense->outstandingAmount(), 2, ',', ' ') : '—' }}</td><td>{{ \App\Models\MemberExpense::STATUSES[$expense->status] }}<br>{{ $expense->decision_note }}@if($expense->claimed_on)<br>Réclamé le {{ $expense->claimed_on->format('d/m/Y') }}@endif</td></tr>
            @empty<tr><td colspan="6">Aucune avance de choriste enregistrée.</td></tr>@endforelse
        </tbody>
    </table></div>
</section>
