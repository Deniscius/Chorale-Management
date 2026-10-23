<?php

namespace App\Services;

use App\Enums\TransactionStatus;
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
