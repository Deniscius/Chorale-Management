<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationNotificationController extends Controller
{
    /**
     * Send a new email verification notification.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if (! config('mail.delivery_enabled')) {
            return back()->with('status', 'verification-email-unavailable');
        }

        try {
            $request->user()->sendEmailVerificationNotification();
        } catch (\Throwable $exception) {
            Log::warning('Email verification delivery failed.', ['user_id' => $request->user()->id, 'exception' => $exception::class]);

            return back()->with('status', 'verification-email-unavailable');
        }

        return back()->with('status', 'verification-link-sent');
    }
}
