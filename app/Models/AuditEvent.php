<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuditEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        self::updating(fn () => throw new \LogicException('Le journal est en ajout uniquement.'));
        self::deleting(fn () => throw new \LogicException('Le journal est en ajout uniquement.'));
    }
}
