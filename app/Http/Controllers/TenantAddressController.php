<?php

namespace App\Http\Controllers;

use App\Services\ChoraleIdentity;
use App\Tenancy\TenantContext;
use Illuminate\Http\Request;

final class TenantAddressController extends Controller
{
    public function show(Request $request)
    {
        $tenant = $request->attributes->get('access_tenant');
        if ($request->user()) {
            return app(DashboardController::class)();
        }

        // Render only the public login identity in this short-lived context.
        return app(TenantContext::class)->run($tenant->id, fn () => response()->view('auth.login', [
            'loginAction' => route('tenant.login', ['slug' => $tenant->slug]),
        ]));
    }

    public function logo(Request $request)
    {
        $tenant = $request->attributes->get('access_tenant');

        return app(TenantContext::class)->run($tenant->id, fn () => app(ChoraleLogoController::class)(app(ChoraleIdentity::class)));
    }
}
