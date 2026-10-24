<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Tenant extends Model
{
    protected $fillable = ['name', 'slug'];

    protected function casts(): array
    {
        return ['trial_started_at' => 'immutable_datetime', 'trial_ends_at' => 'immutable_datetime', 'paid_until' => 'immutable_datetime', 'suspended_at' => 'immutable_datetime', 'paid_perpetual' => 'boolean'];
    }

    public function hasAccess(): bool
    {
        return $this->suspended_at === null && ($this->trial_ends_at?->isFuture() || $this->paid_perpetual || $this->paid_until?->isFuture());
    }
}
