<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\Admin;
use App\Services\Platform\Audit;
use App\Services\Platform\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

final class SessionController extends Controller
{
    public function create()
    {
        return response()->view('platform.login')->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Totp $totp)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255'], 'code' => ['required', 'regex:/^\d{6}$/D']]);
        $email = mb_strtolower(trim($data['email']));
        $keys = ['platform-ip:'.hash('sha256', (string) $request->ip()), 'platform-email:'.hash('sha256', $email)];
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, 5)) {
                throw ValidationException::withMessages(['email' => 'Trop de tentatives. Réessayez dans une minute.']);
            }
            RateLimiter::hit($key, 60);
        }
        $admin = DB::transaction(function () use ($email, $data, $totp) {
            $admin = Admin::where('email', $email)->lockForUpdate()->first();
            // Use a valid dummy hash to avoid a fast path for unknown accounts.
            $validPassword = Hash::check($data['password'], $admin?->password ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
            $counter = $admin && $validPassword && $admin->enabled ? $totp->counter($admin->totp_secret, $data['code'], (int) $admin->last_totp_counter) : null;
            if ($counter === null) {
                throw ValidationException::withMessages(['email' => 'Identifiants ou code de sécurité incorrects.']);
            }
            $admin->forceFill(['last_totp_counter' => $counter, 'last_login_at' => now()])->save();

            return $admin;
        });
        Auth::guard('platform')->login($admin);
        $request->session()->regenerate();
        $request->session()->put(['platform_mfa_id' => $admin->id, 'platform_login_at' => now()->timestamp, 'platform_credential_stamp' => hash('sha256', $admin->password.$admin->getRawOriginal('totp_secret'))]);
        Audit::record('login');

        return redirect()->route('platform.dashboard');
    }

    public function destroy(Request $request)
    {
        Audit::record('logout');
        Auth::guard('platform')->logout();
        $request->session()->forget(['platform_mfa_id', 'platform_login_at', 'platform_credential_stamp']);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
