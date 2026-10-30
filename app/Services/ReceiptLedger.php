<?php

namespace App\Services;

use App\Enums\ContributionStatus;
use App\Enums\DonationType;
use App\Enums\DonorType;
use App\Enums\TransactionStatus;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Receipt creation is explicit in user actions; historical records are never backfilled automatically. */
final class ReceiptLedger
{
    public function saveDonation(?Donation $donation, array $data): Donation
    {
        return DB::transaction(function () use ($donation, $data): Donation {
            $donation = $donation?->exists ? Donation::query()->lockForUpdate()->findOrFail($donation->id) : new Donation;
            $currentCode = $donation->type_code ?? $donation->donation_type?->value;
            $type = app(FinancialCatalog::class)->forWrite('donation', $data['type_code'] ?? $data['donation_type'] ?? $currentCode ?? 'autre', $currentCode);
            $data['type_code'] = $type->code;
            $data['donation_type'] = $type->base_type;
            if ($donation->exists && ! $donation->transaction_id && $donation->donation_type !== DonationType::DonMateriel) {
                throw ValidationException::withMessages(['amount' => 'Ce don historique doit être rapproché par le trésorier avant modification.']);
            }
            $entry = $donation->transaction_id ? Transaction::query()->lockForUpdate()->findOrFail($donation->transaction_id) : null;
            if ($entry) {
                $this->assertDraft($entry);
            }
            $donation->fill($data);
            if ($donation->donor_type === DonorType::Anonyme) {
                $donation->donor_name = null;
                $donation->member_id = null;
            }
            $donation->choir_id = $donation->member_id ? Member::findOrFail($donation->member_id)->choir_id : $donation->choir_id;
            if ($donation->donation_type === DonationType::DonMateriel) {
                if ($entry) {
                    $donation->transaction_id = null;
                    $donation->save();
                    $entry->delete();
                } else {
                    $donation->save();
                }

                return $donation;
            }
            $entry ??= new Transaction;
            $entry->fill([
                'type' => 'recette', 'transaction_date' => $donation->donated_on,
                'description' => $type->label.' : '.($donation->donor_name ?: 'Anonyme'),
                'amount' => $donation->amount, 'member_id' => $donation->member_id,
                'choir_id' => $donation->choir_id, 'category' => app(FinancialCatalog::class)->section('donation'),
                'payment_method' => $data['payment_method'] ?? $entry->payment_method?->value ?? 'especes', 'status' => 'brouillon',
            ]);
            if (! $entry->exists) {
                $entry->created_by = auth()->id();
            }
            $entry->save();
            $donation->transaction_id = $entry->id;
            $donation->save();

            return $donation;
        }, 3);
    }

    public function saveContribution(?Contribution $contribution, array $data): Contribution
    {
        return DB::transaction(function () use ($contribution, $data): Contribution {
            $contribution = $contribution?->exists ? Contribution::query()->lockForUpdate()->findOrFail($contribution->id) : new Contribution;
            $type = app(FinancialCatalog::class)->forWrite('contribution', $data['type_code'] ?? $contribution->type_code ?? 'mensuelle', $contribution->exists ? $contribution->type_code : null);
            $data['type_code'] = $type->code;
            if (! $contribution->exists && $type->frequency_months > 0 && isset($data['year'], $data['month'])) {
                $period = CarbonImmutable::create((int) $data['year'], (int) $data['month'], 1);
                $dueOn = $period->day(min((int) ($type->due_day ?: 1), $period->daysInMonth));
                $data['period_starts_on'] = $period->toDateString();
                $data['due_on'] = $dueOn->toDateString();
                if ((float) ($data['paid_amount'] ?? 0) <= 0 && $dueOn->lt(CarbonImmutable::today())) {
                    $data['status'] = ContributionStatus::Retard;
                }
            }
            $entries = $contribution->exists ? $contribution->receipts()->lockForUpdate()->get() : collect();
            $validated = $entries->filter(fn (Transaction $entry) => $entry->isLocked())->sum(fn ($entry) => $this->cents($entry->amount));
            $previousPaidOn = $contribution->paid_on;
            $contribution->fill($data);
            $paid = $this->cents($contribution->paid_amount ?? 0);
            $due = $this->cents($contribution->due_amount);
            if ($paid < 0 || $paid > $due || $paid < $validated) {
                throw ValidationException::withMessages(['paid_amount' => 'Le montant encaissé doit rester entre le total déjà validé et le montant dû.']);
            }
            if ($validated > 0 && $contribution->isDirty(['member_id', 'year', 'month', 'due_amount', 'type_code'])) {
                throw ValidationException::withMessages(['member_id' => 'Une cotisation avec un règlement validé conserve son choriste, sa période et son montant dû.']);
            }
            // Never infer a second receipt for a historical payment without an accounting link.
            if ($contribution->exists && $entries->isEmpty() && $this->cents($contribution->getRawOriginal('paid_amount') ?? 0) > 0) {
                throw ValidationException::withMessages(['paid_amount' => 'Ce règlement historique doit être rapproché par le trésorier avant modification.']);
            }
            $contribution->status = $paid > 0 ? ($paid >= $due ? ContributionStatus::Payee : ContributionStatus::Partiel)
                : ($contribution->status === ContributionStatus::Retard ? ContributionStatus::Retard : ContributionStatus::Due);
            if ($validated > 0 && $paid === $validated) {
                $contribution->paid_on = $previousPaidOn;
            }
            $contribution->paid_on = $paid > 0 ? ($contribution->paid_on ?? today()) : null;
            $contribution->choir_id = Member::findOrFail($contribution->member_id)->choir_id;
            $contribution->save();
            $pending = $entries->where('status', TransactionStatus::Brouillon)->first();
            $remaining = $paid - $validated;
            if ($remaining > 0) {
                $pending ??= new Transaction;
                $pending->fill([
                    'contribution_id' => $contribution->id, 'type' => 'recette',
                    'transaction_date' => $data['paid_on'] ?? ($pending->exists ? $pending->transaction_date : ($validated > 0 ? today() : $contribution->paid_on)),
                    'description' => $type->label.' '.$contribution->month.'/'.$contribution->year,
                    'amount' => number_format($remaining / 100, 2, '.', ''), 'member_id' => $contribution->member_id,
                    'choir_id' => $contribution->choir_id, 'category' => app(FinancialCatalog::class)->section('contribution'),
                    'payment_method' => $data['payment_method'] ?? $pending->payment_method?->value ?? 'especes', 'status' => 'brouillon',
                ]);
                if (! $pending->exists) {
                    $pending->created_by = auth()->id();
                }
                $pending->save();
            } elseif ($pending) {
                $pending->delete();
            }

            return $contribution;
        }, 3);
    }

    public function deleteDonation(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $donation = Donation::query()->lockForUpdate()->findOrFail($id);
            $entry = $donation->transaction_id ? Transaction::query()->lockForUpdate()->findOrFail($donation->transaction_id) : null;
            if ($entry) {
                $this->assertDraft($entry);
            }
            $donation->delete();
            $entry?->delete();
        });
    }

    public function deleteContribution(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $contribution = Contribution::query()->lockForUpdate()->findOrFail($id);
            $entries = $contribution->receipts()->lockForUpdate()->get();
            foreach ($entries as $entry) {
                $this->assertDraft($entry);
            }
            foreach ($entries as $entry) {
                $entry->delete();
            }
            $contribution->delete();
        });
    }

    private function assertDraft(Transaction $entry): void
    {
        if ($entry->status !== TransactionStatus::Brouillon) {
            throw ValidationException::withMessages(['amount' => 'Cette recette a déjà été traitée. Le trésorier doit enregistrer une écriture de régularisation.']);
        }
    }

    private function cents(string|int|float $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
