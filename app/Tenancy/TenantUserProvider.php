<?php

namespace App\Tenancy;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

final class TenantUserProvider extends EloquentUserProvider
{
    // Authentication alone resolves a globally unique login. Business user lists remain scoped.
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        return app(TenantContext::class)->run((int) $user->tenant_id, fn () => parent::updateRememberToken($user, $token));
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        return app(TenantContext::class)->run((int) $user->tenant_id, fn () => parent::rehashPasswordIfRequired($user, $credentials, $force));
    }
}
