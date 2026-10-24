<?php

namespace App\Http\Controllers;

use App\Services\TenantRegistration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class TenantRegistrationController extends Controller
{
    public function create()
    {
        abort_unless(config('tenancy.enabled') && config('tenancy.registration'), 404);

        return view('auth.create-space');
    }

    public function store(Request $request, TenantRegistration $registration)
    {
        abort_unless(config('tenancy.enabled') && config('tenancy.registration'), 404);
        // Honeypot: reject automated submissions before creating any data.
        if ($request->filled('website')) {
            throw ValidationException::withMessages(['organization' => 'L’inscription n’a pas pu être validée. Réessayez.']);
        }
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email'))), 'username' => mb_strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate([
            'organization' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9._-]*$/', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);
        try {
            $user = $registration->create($data);
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['email' => 'Cette adresse ou cet identifiant est déjà utilisé.']);
        }
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }
}
