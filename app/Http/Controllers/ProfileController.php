<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Member;
use App\Observers\PersonalInformationObserver;
use App\Services\AccountWriter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        return view('profile.edit', [
            'user' => $request->user()->load('member'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request) {
                $data = $request->validated();
                $user = $request->user();
                $member = $user->member_id ? Member::lockForUpdate()->findOrFail($user->member_id) : null;
                if ($member) {
                    $member->fill(Arr::only($data, PersonalInformationObserver::MEMBER_FIELDS));
                    $member->save();
                    $data['name'] = $member->first_name.' '.$member->last_name;
                }
                app(AccountWriter::class)->save($user, Arr::only($data, ['name', 'username', 'email']));
            }, 3);
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
