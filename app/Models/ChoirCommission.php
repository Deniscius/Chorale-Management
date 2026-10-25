<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChoirCommission extends Model
{
    use BelongsToTenant;

    protected $fillable = ['name', 'name_key', 'kind', 'mission', 'starts_on', 'ends_on', 'archived_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'archived_at' => 'datetime'];
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'choir_commission_member');
    }

    public function scopeInStatus(Builder $query, string $status): void
    {
        $today = today()->toDateString();
        match ($status) {
            'archived' => $query->whereNotNull('archived_at'),
            'scheduled' => $query->whereNull('archived_at')->whereDate('starts_on', '>', $today),
            'completed' => $query->whereNull('archived_at')->whereDate('ends_on', '<', $today),
            'active' => $query->whereNull('archived_at')->whereDate('starts_on', '<=', $today)->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today)),
            default => null,
        };
    }

    public function getStatusAttribute(): string
    {
        return match (true) {
            $this->archived_at !== null => 'archived',
            $this->starts_on->isAfter(today()) => 'scheduled',
            $this->ends_on !== null && $this->ends_on->isBefore(today()) => 'completed',
            default => 'active',
        };
    }

    public static function statuses(): array
    {
        return ['active' => 'En cours', 'scheduled' => 'À venir', 'completed' => 'Terminées', 'archived' => 'Archivées'];
    }
}
