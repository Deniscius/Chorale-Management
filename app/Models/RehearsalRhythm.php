<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class RehearsalRhythm extends Model
{
    use BelongsToTenant;

    protected $fillable = ['choir_id', 'weekday', 'starts_at', 'ends_at', 'venue', 'effective_from', 'effective_until', 'is_active'];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function choir(): BelongsTo
    {
        return $this->belongsTo(Choir::class);
    }

    public function rehearsals(): HasMany
    {
        return $this->hasMany(Rehearsal::class, 'rehearsal_rhythm_id');
    }
}
