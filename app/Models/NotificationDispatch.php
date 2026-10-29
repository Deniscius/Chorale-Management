<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NotificationDispatch extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'user_id', 'fingerprint', 'kind', 'subject_type', 'subject_id',
        'scheduled_for', 'attempts', 'notified_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'date',
            'attempts' => 'integer',
            'notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
