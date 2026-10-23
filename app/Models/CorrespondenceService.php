<?php

namespace App\Models;

use Database\Factories\CorrespondenceServiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorrespondenceService extends Model
{
    /** @use HasFactory<CorrespondenceServiceFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'responsible_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'responsible_id');
    }

    public function correspondences(): HasMany
    {
        return $this->hasMany(Correspondence::class, 'service_id');
    }
}
