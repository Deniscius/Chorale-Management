<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Services\TransactionReferenceGenerator;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    protected $fillable = [
        'choir_id',
        'contribution_id',
        'reference',
        'type',
        'transaction_date',
        'description',
        'amount',
        'account_id',
        'budget_id',
        'category',
        'payment_method',
        'status',
        'member_id',
        'activity_id',
        'document_path',
        'validated_by',
        'validated_at',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'status' => TransactionStatus::class,
            'payment_method' => PaymentMethod::class,
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'validated_at' => 'datetime',
        ];
    }

    /**
     * La référence d'écriture (OP/2026/0001) est attribuée automatiquement.
     */
    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            if (blank($transaction->reference)) {
                $transaction->reference = app(TransactionReferenceGenerator::class)->next(
                    $transaction->transaction_date?->year,
                );
            }
        });
    }

    public function isLocked(): bool
    {
        return in_array($this->status, [TransactionStatus::Validee, TransactionStatus::Cloturee], true);
    }

    public function isReceipt(): bool
    {
        return $this->contribution_id !== null || Donation::where('transaction_id', $this->id)->exists();
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Les opérations validées seulement entrent dans lessoldes.
     */
    public function scopeValidated(Builder $query): Builder
    {
        return $query->whereIn('status', [TransactionStatus::Validee->value, TransactionStatus::Cloturee->value]);
    }

    public function scopeOfType(Builder $query, TransactionType $type): Builder
    {
        return $query->where('type', $type->value);
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query->when($from, fn (Builder $q) => $q->whereDate('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->whereDate('transaction_date', '<=', $to));
    }
}
