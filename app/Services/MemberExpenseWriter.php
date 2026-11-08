<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Member;
use App\Models\MemberExpense;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class MemberExpenseWriter
{
    public function create(array $data): MemberExpense
    {
        Gate::authorize('manage-module', UserRole::MODULE_COMPTABILITE);
        $data = Validator::make($data, [
            'member_id' => ['required', 'integer'], 'activity_id' => ['nullable', 'integer'],
            'incurred_on' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'claimed_on' => ['nullable', 'date', 'after_or_equal:incurred_on', 'before_or_equal:today'],
            'evidence_reference' => ['nullable', 'string', 'max:255'], 'notes' => ['nullable', 'string', 'max:2000'],
        ])->validate();
        $data['incurred_on'] = CarbonImmutable::parse($data['incurred_on'])->toDateString();
        $data['claimed_on'] = empty($data['claimed_on']) ? null : CarbonImmutable::parse($data['claimed_on'])->toDateString();
        Member::query()->findOrFail($data['member_id']);
        if ($data['activity_id'] ?? null) {
            Activity::query()->findOrFail($data['activity_id']);
        }

        $expense = new MemberExpense($data);
        $expense->created_by = auth()->id();
        $expense->save();

        return $expense;
    }

    public function decide(int $id, string $status, string $note): void
    {
        Gate::authorize('manage-module', UserRole::MODULE_COMPTABILITE);
        Validator::make(['status' => $status, 'decision_note' => trim($note)], [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => ['required', 'string', 'max:2000'],
        ])->validate();
        DB::transaction(function () use ($id, $status, $note): void {
            $expense = MemberExpense::query()->lockForUpdate()->findOrFail($id);
            if ($expense->repayments()->exists()) {
                throw ValidationException::withMessages(['operation' => 'Cette dépense a déjà été remboursée en partie ou en totalité.']);
            }
            $expense->forceFill(['status' => $status, 'decision_note' => trim($note),
                'decided_by' => auth()->id(), 'decided_at' => now()])->save();
        }, 3);
    }

    public function recordClaim(int $id): void
    {
        Gate::authorize('manage-module', UserRole::MODULE_COMPTABILITE);
        DB::transaction(function () use ($id): void {
            $expense = MemberExpense::query()->lockForUpdate()->findOrFail($id);
            if (! $expense->claimed_on) {
                $expense->update(['claimed_on' => today()]);
            }
        }, 3);
    }

    /** Record an actual payment, never a bank transfer or a second expense commitment. */
    public function reimburse(int $id, array $data): Transaction
    {
        Gate::authorize('manage-module', UserRole::MODULE_COMPTABILITE);
        $data = Validator::make($data, [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:9999999999.99'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:2000'], 'reimbursement_key' => ['required', 'uuid'],
        ])->validate();
        $data['transaction_date'] = CarbonImmutable::parse($data['transaction_date'])->toDateString();

        return DB::transaction(function () use ($id, $data): Transaction {
            $expense = MemberExpense::query()->lockForUpdate()->findOrFail($id);
            $existing = Transaction::query()->where('reimbursement_key', $data['reimbursement_key'])->first();
            if ($existing) {
                if ((int) $existing->member_expense_id !== $id
                    || (int) round((float) $existing->amount * 100) !== (int) round((float) $data['amount'] * 100)
                    || (int) $existing->account_id !== (int) $data['account_id']
                    || $existing->transaction_date->toDateString() !== $data['transaction_date']
                    || $existing->payment_method->value !== $data['payment_method']) {
                    throw ValidationException::withMessages(['operation' => 'Cette référence de paiement est déjà utilisée. Rechargez le détail avant de saisir un autre paiement.']);
                }

                return $existing;
            }
            $account = Account::query()->where('is_active', true)->lockForUpdate()->find($data['account_id']);
            if (! $account) {
                throw ValidationException::withMessages(['account_id' => 'Sélectionnez un compte actif de votre chorale.']);
            }
            if ($expense->status !== 'approved') {
                throw ValidationException::withMessages(['operation' => 'Le remboursement doit être approuvé avant tout paiement.']);
            }
            if ($data['transaction_date'] < $expense->incurred_on->toDateString()) {
                throw ValidationException::withMessages(['transaction_date' => 'Le paiement ne peut pas précéder la dépense.']);
            }
            $remainingCents = (int) round($expense->outstandingAmount() * 100);
            if ((int) round((float) $data['amount'] * 100) > $remainingCents) {
                throw ValidationException::withMessages(['amount' => 'Le montant dépasse le reste à rembourser.']);
            }

            return Transaction::query()->create($data + [
                'member_expense_id' => $expense->id, 'activity_id' => $expense->activity_id,
                'member_id' => $expense->member_id, 'type' => TransactionType::Depense,
                'description' => 'Remboursement avance #'.$expense->id.' — '.mb_substr($expense->description, 0, 180),
                'category' => 'Remboursements aux choristes', 'status' => TransactionStatus::Validee,
                'created_by' => auth()->id(), 'validated_by' => auth()->id(), 'validated_at' => now(),
            ]);
        }, 3);
    }
}
