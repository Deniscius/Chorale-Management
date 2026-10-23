<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use Database\Factories\RehearsalAttendanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RehearsalAttendance extends Model
{
    /** @use HasFactory<RehearsalAttendanceFactory> */
    use HasFactory;

    protected $fillable = [
        'rehearsal_id',
        'member_id',
        'status',
        'excuse_reason',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'checked_at' => 'datetime',
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
}
