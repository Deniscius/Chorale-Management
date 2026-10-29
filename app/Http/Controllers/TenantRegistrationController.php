<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Rules\PersonName;
use App\Rules\ReadableText;
use App\Services\ChurchProjectClient;
use App\Services\TenantRegistration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class TenantRegistrationController extends Controller
{
    public function create(ChurchProjectClient $churchProject)
    {
        abort_unless(config('tenancy.enabled') && config('tenancy.registration'), 404);

        return view('auth.create-space', ['churchProjectEnabled' => $churchProject->enabled()]);
    }

    public function store(Request $request, TenantRegistration $registration, ChurchProjectClient $churchProject)
    {
        abort_unless(config('tenancy.enabled') && config('tenancy.registration'), 404);
        // Honeypot: reject automated submissions before creating any data.
        if ($request->filled('website')) {
            throw ValidationException::withMessages(['organization' => 'L’inscription n’a pas pu être validée. Réessayez.']);
        }
        $request->merge([
            'organization' => trim(preg_replace('/\\s+/u', ' ', (string) $request->input('organization'))),
            'name' => trim(preg_replace('/\\s+/u', ' ', (string) $request->input('name'))),
            'email' => mb_strtolower(trim((string) $request->input('email'))),
            'username' => mb_strtolower(trim((string) $request->input('username'))),
        ]);
        $data = $request->validate([
            'organization' => [$churchProject->enabled() ? 'nullable' : 'required', 'string', 'max:120', new ReadableText],
            'invitation' => [$churchProject->enabled() ? 'required' : 'nullable', 'string', 'min:40', 'max:200'],
            'name' => ['required', 'string', 'max:120', new PersonName],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9._-]*$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);
        if ($churchProject->enabled()) {
            $claim = $churchProject->resolveInvitation($data['invitation']);
            $data['organization'] = $claim['choraleNom'];
            $data['church_project_chorale_id'] = $claim['choralePublicId'];
            $data['church_project_paroisse_id'] = $claim['paroissePublicId'];
            $data['church_project_doyenne_id'] = $claim['doyennePublicId'];
            if (Tenant::query()->where('church_project_chorale_id', $claim['choralePublicId'])->exists()) {
                throw ValidationException::withMessages(['invitation' => 'Cette chorale possède déjà un espace. Demandez à son administrateur de vous créer un compte.']);
            }
        }
        try {
            $user = $registration->create($data);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['email' => 'Cette adresse ou cet identifiant est déjà utilisé.']);
        }
        Auth::login($user);
        $request->session()->regenerate();
        if ($churchProject->enabled()) {
            $churchProject->consumeInvitation($data['invitation']);
        }

        return redirect()->route('dashboard');
    }
}
