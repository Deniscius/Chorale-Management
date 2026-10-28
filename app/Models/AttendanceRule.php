<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

final class AttendanceRule extends Model
{
    use BelongsToTenant;

    protected $attributes = [
        'financial_tracking_enabled' => false,
        'rehearsal_absence_amount' => 0,
        'activity_absence_amount' => 0,
        'late_amount' => 0,
        'late_threshold_minutes' => 1,
    ];

    protected $fillable = [
        'financial_tracking_enabled', 'rehearsal_absence_amount', 'activity_absence_amount',
        'late_amount', 'late_threshold_minutes', 'effective_from', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'financial_tracking_enabled' => 'boolean',
            'rehearsal_absence_amount' => 'decimal:2',
            'activity_absence_amount' => 'decimal:2',
            'late_amount' => 'decimal:2',
            'late_threshold_minutes' => 'integer',
            'effective_from' => 'date',
        ];
    }
}
