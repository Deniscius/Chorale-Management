<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class PlatformAccess
{
    public function handle(Request $request, Closure $next): mixed
    {
        $admin = Auth::guard('platform')->user();
        $valid = $admin?->enabled && (int) $request->session()->get('platform_mfa_id') === $admin->id
            && hash_equals(hash('sha256', $admin->password.$admin->getRawOriginal('totp_secret')), (string) $request->session()->get('platform_credential_stamp'))
            && (int) $request->session()->get('platform_login_at') > now()->subHours(8)->timestamp;
        if (! $valid) {
            Auth::guard('platform')->logout();
            $request->session()->forget(['platform_mfa_id', 'platform_login_at', 'platform_credential_stamp']);

            return redirect()->route('platform.login');
        }
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
