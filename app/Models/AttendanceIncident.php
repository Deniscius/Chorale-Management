<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AttendanceIncident extends Model
{
    use BelongsToTenant;

    public const PENDING = 'pending';

    public const WAIVED = 'waived';

    public const CANCELLED = 'cancelled';

    protected $fillable = [
        'member_id', 'rehearsal_attendance_id', 'activity_participant_id', 'source_type',
        'occurred_on', 'event_label', 'incident_type', 'minutes_late', 'amount', 'status',
        'rule_snapshot', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'amount' => 'decimal:2',
            'minutes_late' => 'integer',
            'rule_snapshot' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function rehearsalAttendance(): BelongsTo
    {
        return $this->belongsTo(RehearsalAttendance::class);
    }

    public function activityParticipant(): BelongsTo
    {
        return $this->belongsTo(ActivityParticipant::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
