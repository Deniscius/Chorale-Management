<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipant extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'member_id',
        'role',
        'status',
        'hours',
        'notes',
        'minutes_late',
        'excuse_reason',
        'checked_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'hours' => 'decimal:2',
            'minutes_late' => 'integer',
            'checked_at' => 'datetime',
        ];
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
