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

    /**
     * Prevent malformed route parameters from reaching UUID columns.
     *
     * PostgreSQL rejects a numeric value when it is compared to a UUID,
     * whereas SQLite silently accepts it. Returning an empty query keeps the
     * route-binding contract identical on both database engines: a 404.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (! is_string($value) || ! Str::isUuid($value)) {
            return $query->whereRaw('1 = 0');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
