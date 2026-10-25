<?php

namespace App\Models;

use App\Services\FinancialCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

final class Tenant extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        self::created(fn (self $tenant) => FinancialCatalog::seed((int) $tenant->id));
    }

    protected function casts(): array
    {
        return ['trial_started_at' => 'immutable_datetime', 'trial_ends_at' => 'immutable_datetime', 'paid_until' => 'immutable_datetime', 'suspended_at' => 'immutable_datetime', 'paid_perpetual' => 'boolean'];
    }

    public function hasAccess(): bool
    {
        return $this->suspended_at === null && ($this->trial_ends_at?->isFuture() || $this->paid_perpetual || $this->paid_until?->isFuture());
    }

    public function accessLabel(): string
    {
        return match (true) {
            $this->suspended_at !== null => 'Suspendu',
            $this->paid_perpetual => 'Accès permanent',
            $this->paid_until?->isFuture() => 'Accès payant',
            $this->trial_ends_at?->isFuture() => 'En essai',
            default => 'Expiré',
        };
    }

    public function accessEndsAt(): ?CarbonImmutable
    {
        if ($this->paid_perpetual) {
            return null;
        }
        if (! $this->paid_until) {
            return $this->trial_ends_at;
        }

        return ! $this->trial_ends_at || $this->paid_until->gt($this->trial_ends_at) ? $this->paid_until : $this->trial_ends_at;
    }
}
