<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class MemberExpense extends Model
{
    use BelongsToTenant;

    public const STATUSES = ['pending' => 'À examiner', 'approved' => 'Remboursement approuvé', 'rejected' => 'Remboursement refusé'];

    protected $fillable = ['member_id', 'activity_id', 'incurred_on', 'description', 'amount', 'claimed_on', 'evidence_reference', 'notes'];

    protected function casts(): array
    {
        return ['incurred_on' => 'date', 'claimed_on' => 'date', 'amount' => 'decimal:2', 'decided_at' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class)->withTrashed();
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(Transaction::class)->validated();
    }

    public function repaidAmount(): float
    {
        if (array_key_exists('repayments_sum_amount', $this->getAttributes())) {
            return (float) $this->repayments_sum_amount;
        }

        return (float) ($this->relationLoaded('repayments') ? $this->repayments->sum('amount') : $this->repayments()->sum('amount'));
    }

    public function outstandingAmount(): float
    {
        return $this->status === 'approved' ? max(0, round((float) $this->amount - $this->repaidAmount(), 2)) : 0;
    }
}
