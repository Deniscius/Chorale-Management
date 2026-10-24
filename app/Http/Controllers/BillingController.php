<?php

namespace App\Http\Controllers;

use App\Services\ChariowLicense;
use Illuminate\Http\Request;

final class BillingController extends Controller
{
    public function show(Request $request)
    {
        abort_unless(config('tenancy.enabled'), 404);
        $url = (string) config('tenancy.chariow.payment_url');
        $url = filter_var($url, FILTER_VALIDATE_URL) && parse_url($url, PHP_URL_SCHEME) === 'https' ? $url : null;

        return response()->view('billing.show', ['tenant' => $request->attributes->get('tenant'), 'paymentUrl' => $url, 'canManage' => $request->user()->isAdmin(), 'activationReady' => filled(config('tenancy.chariow.api_key')) && filled(config('tenancy.chariow.product_id'))])
            ->header('Cache-Control', 'no-store, private');
    }

    public function activate(Request $request, ChariowLicense $licenses)
    {
        abort_unless(config('tenancy.enabled') && $request->user()->isAdmin(), 403);
        if (! $request->user()->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }
        $data = $request->validate(['license_key' => ['required', 'string', 'max:150', 'regex:/^[a-zA-Z0-9_-]+$/']]);
        $licenses->activate($request->attributes->get('tenant'), $request->user(), $data['license_key']);

        return redirect()->route('dashboard')->with('status', 'Votre accès est activé.');
    }
}
