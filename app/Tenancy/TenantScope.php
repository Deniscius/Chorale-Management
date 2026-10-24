<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (config('tenancy.enabled')) {
            $id = app(TenantContext::class)->id();
            $id === null ? $builder->whereRaw('1 = 0') : $builder->where($model->qualifyColumn('tenant_id'), $id);
        }
    }
}
