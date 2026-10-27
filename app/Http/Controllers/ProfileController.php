<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\ChoirCommission;
use App\Services\AccountWriter;
use App\Services\CommissionAccess;
use App\Services\PersonalProfileWriter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user()->load('member');
        $delegations = app(CommissionAccess::class)->assignments($user)->keyBy('id');
        $commissions = ChoirCommission::inStatus('active')->where(fn ($q) => $q->whereIn('id', $delegations->keys())
            ->when(! $user->member_id || $user->member, fn ($q) => $q->orWhere('associate_id', $user->id)))->orderBy('name')->get();

        return view('profile.edit', [
            'user' => $user,
            'commissions' => $commissions,
            'delegations' => $delegations,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            app(PersonalProfileWriter::class)->save($request->user(), $request->validated());
        } catch (UniqueConstraintViolationException $e) {
            throw ValidationException::withMessages(['email' => 'Cette adresse vient d’être utilisée par un autre compte. Choisissez une autre adresse.']);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        app(AccountWriter::class)->delete($user, 'userDeletion');

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
