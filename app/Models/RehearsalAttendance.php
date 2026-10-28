<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use App\Tenancy\BelongsToTenant;
use Database\Factories\RehearsalAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RehearsalAttendance extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<RehearsalAttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'rehearsal_id',
        'member_id',
        'status',
        'excuse_reason',
        'checked_at',
        'minutes_late',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'checked_at' => 'datetime',
            'minutes_late' => 'integer',
        ];
    }

    public function rehearsal(): BelongsTo
    {
        return $this->belongsTo(Rehearsal::class);
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
