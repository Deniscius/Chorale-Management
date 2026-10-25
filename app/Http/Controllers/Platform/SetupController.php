<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Platform\Admin;
use App\Services\Platform\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class SetupController extends Controller
{
    private function available(): void
    {
        abort_if(Admin::exists(), 404);
        abort_unless(config('deployment.bootstrap_enabled') && strlen((string) config('deployment.setup_token')) >= 32, 503, 'L’installation propriétaire doit être activée dans les paramètres de l’hébergement.');
    }

    public function create(Request $request, Totp $totp)
    {
        $this->available();
        if (! $request->session()->has('platform_setup_secret') || (int) $request->session()->get('platform_setup_issued_at') < now()->subMinutes(10)->timestamp) {
            $request->session()->put(['platform_setup_secret' => Crypt::encryptString($totp->secret()), 'platform_setup_issued_at' => now()->timestamp]);
        }
        $secret = Crypt::decryptString($request->session()->get('platform_setup_secret'));

        return response()->view('platform.setup', compact('secret'))->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, Totp $totp)
    {
        $this->available();
        $data = $request->validate([
            'setup_token' => ['required', 'string', 'min:32', 'max:256'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', 'max:255', Password::min(16)->letters()->numbers()],
            'code' => ['required', 'regex:/^\d{6}$/D'],
        ]);
        if (! hash_equals(hash('sha256', (string) config('deployment.setup_token')), hash('sha256', $data['setup_token']))) {
            throw ValidationException::withMessages(['setup_token' => 'Clé d’installation incorrecte.']);
        }
        $issued = (int) $request->session()->get('platform_setup_issued_at');
        if (! $request->session()->has('platform_setup_secret') || $issued < now()->subMinutes(10)->timestamp || $issued > now()->timestamp) {
            $request->session()->forget(['platform_setup_secret', 'platform_setup_issued_at']);
            throw ValidationException::withMessages(['code' => 'Cette préparation a expiré. Rechargez la page pour une nouvelle clé d’authentification.']);
        }
        $secret = Crypt::decryptString($request->session()->get('platform_setup_secret'));
        $counter = $totp->counter($secret, $data['code'], -1);
        if ($counter === null) {
            throw ValidationException::withMessages(['code' => 'Le code d’authentification est incorrect.']);
        }
        DB::transaction(function () use ($data, $secret, $counter) {
            DB::table('tenants')->where('id', 1)->lockForUpdate()->firstOrFail();
            abort_if(Admin::exists(), 404);
            $admin = Admin::create(['name' => $data['name'], 'email' => mb_strtolower(trim($data['email'])), 'password' => $data['password'], 'totp_secret' => $secret, 'last_totp_counter' => $counter, 'enabled' => true]);
            DB::table('platform_audit_events')->insert(['platform_admin_id' => $admin->id, 'action' => 'admin_created', 'reason' => 'Installation propriétaire avec clé privée et confirmation TOTP', 'created_at' => now()]);
        });
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->with('status', 'Votre espace propriétaire est prêt. Connectez-vous avec le prochain code de votre application d’authentification.');
    }
}
