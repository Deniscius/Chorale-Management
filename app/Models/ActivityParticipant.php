<?php

namespace App\Models;

use App\Enums\ParticipationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'activity_id',
        'member_id',
        'role',
        'status',
        'hours',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ParticipationStatus::class,
            'hours' => 'decimal:2',
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
}
