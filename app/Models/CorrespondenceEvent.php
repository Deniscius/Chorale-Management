<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Database\Factories\CorrespondenceEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CorrespondenceEvent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<CorrespondenceEventFactory> */
    use HasFactory;

    protected $fillable = [
        'correspondence_id',
        'user_id',
        'action',
        'note',
    ];

    public function correspondence(): BelongsTo
    {
        return $this->belongsTo(Correspondence::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
