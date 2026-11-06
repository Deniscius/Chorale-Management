<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Keeps internal numeric keys for relations and exposes an opaque UUID in URLs.
 *
 * UUIDs reduce identifier enumeration, while tenant scopes and authorization
 * remain the security boundary for every resource.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (Model $model): void {
            if (blank($model->getAttribute('public_id'))) {
                $model->setAttribute('public_id', (string) Str::uuid());
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
