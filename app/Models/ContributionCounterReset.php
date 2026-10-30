<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ContributionCounterReset extends Model
{
    use BelongsToTenant;

    protected $fillable = ['member_id', 'type_code', 'starts_on', 'reason', 'approved_by'];

    protected function casts(): array
    {
        return ['starts_on' => 'date'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
