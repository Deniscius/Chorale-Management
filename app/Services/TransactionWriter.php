<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\MemberExpense;
use App\Models\Performance;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TransactionWriter
{
    public function save(?Transaction $entry, array $data): Transaction
    {
        return DB::transaction(function () use ($entry, $data): Transaction {
            $entry = $entry?->exists ? Transaction::query()->lockForUpdate()->findOrFail($entry->id) : new Transaction;
            if ($entry->exists) {
                $this->assertEditable($entry);
            }
            if ($data['account_id'] ?? null) {
                $account = Account::query()->findOrFail($data['account_id']);
                if (! $account->is_active && (int) $entry->account_id !== (int) $account->id) {
                    throw ValidationException::withMessages(['account_id' => 'Ce compte est désactivé. Choisissez un compte actif.']);
                }
            }
            if ($data['activity_id'] ?? null) {
                Activity::query()->lockForUpdate()->findOrFail($data['activity_id']);
            }
            foreach (['budget_id' => Budget::class, 'member_id' => Member::class, 'performance_id' => Performance::class] as $field => $model) {
                if ($data[$field] ?? null) {
                    $model::query()->findOrFail($data[$field]);
                }
            }
            $entry->fill($data);
            if (! $entry->exists) {
                $entry->created_by = auth()->id();
            }
            if (in_array($entry->status, [TransactionStatus::Validee, TransactionStatus::Cloturee], true)) {
                $entry->validated_by = auth()->id();
                $entry->validated_at = now();
            } else {
                $entry->validated_by = null;
                $entry->validated_at = null;
            }
            $entry->save();

            return $entry;
        }, 3);
    }

    /** Classify a draft without changing the financial data owned by its source. */
    public function assignContext(int $id, ?int $activityId, ?int $accountId): void
    {
        DB::transaction(function () use ($id, $activityId, $accountId): void {
            $entry = Transaction::query()->lockForUpdate()->findOrFail($id);
            if ($entry->status !== TransactionStatus::Brouillon) {
                throw ValidationException::withMessages(['classificationActivity' => 'Seul un brouillon peut être rattaché. Une écriture validée nécessite une régularisation.']);
            }
            if ($entry->contribution_id) {
                $origin = Contribution::query()->findOrFail($entry->contribution_id);
            } elseif ($entry->member_expense_id) {
                $origin = MemberExpense::query()->findOrFail($entry->member_expense_id);
            }
            if (isset($origin) && (int) $origin->activity_id !== (int) $activityId) {
                throw ValidationException::withMessages(['classificationActivity' => 'L’activité de ce règlement est définie par son document d’origine et doit être conservée.']);
            }
            if ($activityId) {
                Activity::query()->lockForUpdate()->findOrFail($activityId);
            }
            if ($accountId) {
                $account = Account::query()->findOrFail($accountId);
                if (! $account->is_active && (int) $entry->account_id !== $accountId) {
                    throw ValidationException::withMessages(['classificationAccount' => 'Choisissez un compte actif.']);
                }
            }
            $entry->update(['activity_id' => $activityId, 'account_id' => $accountId]);
        }, 3);
    }

    public function delete(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $entry = Transaction::query()->lockForUpdate()->findOrFail($id);
            $this->assertEditable($entry);
            $entry->delete();
        });
    }

    public function transition(int $id, TransactionStatus $status): void
    {
        DB::transaction(function () use ($id, $status): void {
            $entry = Transaction::query()->lockForUpdate()->findOrFail($id);
            if ($entry->status !== TransactionStatus::Brouillon || ($status === TransactionStatus::Rejetee && $entry->isReceipt())) {
                throw ValidationException::withMessages(['operation' => 'Cette opération est protégée ou a déjà été traitée.']);
            }
            $entry->update(['status' => $status, 'validated_by' => auth()->id(), 'validated_at' => now()]);
        });
    }

    private function assertEditable(Transaction $entry): void
    {
        if ($entry->isLocked() || $entry->isReceipt()) {
            throw ValidationException::withMessages(['operation' => 'Opération protégée : modifiez le règlement d’origine ou créez une écriture de régularisation.']);
        }
    }
}
