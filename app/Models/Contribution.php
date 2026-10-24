<?php

namespace App\Models;

use App\Enums\ContributionStatus;
use App\Support\Months;
use App\Tenancy\BelongsToTenant;
use Database\Factories\ContributionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contribution extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ContributionFactory> */
    use HasFactory;

    protected $fillable = [
        'member_id',
        'choir_id',
        'year',
        'month',
        'due_amount',
        'paid_amount',
        'paid_on',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'due_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'paid_on' => 'date',
            'status' => ContributionStatus::class,
        ];
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function isPaid(): bool
    {
        return $this->status === ContributionStatus::Payee;
    }

    public function balance(): float
    {
        return (float) $this->due_amount - (float) $this->paid_amount;
    }

    public function monthLabel(): string
    {
        return Months::label($this->month);
    }
}
