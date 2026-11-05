<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Tenant;
use App\Services\SessionAuditRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Journaliser uniquement l'identité et l'heure, jamais les identifiants secrets.
        $user = $request->user();
        if ($user) {
            app(SessionAuditRecorder::class)->record($user, 'login');
        }

        if ($tenant = $request->attributes->get('access_tenant')) {
            $request->session()->forget('url.intended');

            return redirect()->route('tenant.entry', ['slug' => $tenant->slug]);
        }

        // Une connexion depuis l'adresse générale doit rejoindre l'espace de
        // l'utilisateur. L'URL devient ainsi explicite et l'utilisateur ne
        // peut jamais se retrouver dans une autre chorale par erreur.
        if (config('tenancy.enabled') && $user?->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);

            if ($tenant) {
                $request->session()->forget('url.intended');

                return redirect()->route('tenant.entry', ['slug' => $tenant->slug]);
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user) {
            app(SessionAuditRecorder::class)->record($user, 'logout');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
