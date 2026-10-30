<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContributionExemption extends Model
{
    use BelongsToTenant;

    protected $fillable = ['member_id', 'type_code', 'starts_on', 'ends_on', 'clears_prior_arrears', 'reason', 'approved_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'clears_prior_arrears' => 'boolean'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function covers(\DateTimeInterface $date): bool
    {
        return $this->starts_on->lte($date) && ($this->ends_on === null || $this->ends_on->gte($date));
    }
}
