<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MemberOnboarding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class MemberActivationController extends Controller
{
    public function show(string $token, MemberOnboarding $onboarding)
    {
        return response()->view('auth.member-activation', $onboarding->details($token) + ['token' => $token])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function store(Request $request, string $token, MemberOnboarding $onboarding)
    {
        $user = $onboarding->activate($token, $request->only('username', 'email', 'password', 'password_confirmation'));
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')->with('status', 'Votre compte est prêt. Bienvenue !')->header('Cache-Control', 'no-store, private');
    }
}
