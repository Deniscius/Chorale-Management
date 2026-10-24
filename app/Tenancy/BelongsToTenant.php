<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
        static::saving(function (Model $model): void {
            $id = app(TenantContext::class)->requireId();
            if ($model->exists) {
                abort_unless((int) $model->getRawOriginal('tenant_id') === $id, 404);
            }
            if ($model->exists || $model->getAttribute('tenant_id') !== null) {
                abort_unless((int) $model->getAttribute('tenant_id') === $id, 404);
            }
            $model->setAttribute('tenant_id', $id);
            if (config('tenancy.enabled')) {
                foreach (config('tenant-tables.relations.'.$model->getTable(), []) as $column => $parent) {
                    if ($model->getAttribute($column) !== null && (! $model->exists || $model->isDirty($column))) {
                        if (! app(TenantContext::class)->table($parent)->where('id', $model->getAttribute($column))->exists()) {
                            throw ValidationException::withMessages([$column => 'Sélectionnez une donnée de votre espace.']);
                        }
                    }
                }
            }
        });
        static::deleting(function (Model $model): void {
            abort_unless((int) $model->getRawOriginal('tenant_id') === app(TenantContext::class)->requireId(), 404);
            abort_unless((int) $model->getAttribute('tenant_id') === app(TenantContext::class)->requireId(), 404);
        });
    }

    public function newQueryForRestoration($ids)
    {
        return $this->newQuery()->whereKey($ids);
    }

    protected function newBelongsToMany(Builder $query, Model $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null)
    {
        return parent::newBelongsToMany($query, $parent, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName)
            ->withPivotValue('tenant_id', app(TenantContext::class)->requireId());
    }
}
