<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\Middleware\AuthenticateSession;

final class AuthenticateChoraleSession extends AuthenticateSession
{
    public function handle($request, Closure $next)
    {
        if ($request->hasSession() && $request->user()) {
            $id = (int) $request->user()->getAuthIdentifier();
            if ($request->session()->has('chorale_session_user') && (int) $request->session()->get('chorale_session_user') !== $id) {
                $request->session()->forget('password_hash_web');
            }
            $request->session()->put('chorale_session_user', $id);
        }

        return parent::handle($request, $next);
    }

    protected function logout($request)
    {
        $this->guard()->logoutCurrentDevice();
        // The owner guard shares the browser session, but has separate credentials.
        $request->session()->forget(['password_hash_web', 'chorale_session_user']);
        $request->session()->regenerate(true);

        throw new AuthenticationException('Unauthenticated.', ['web'], route('login'));
    }
}
