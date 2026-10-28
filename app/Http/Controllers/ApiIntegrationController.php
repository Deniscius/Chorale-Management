<?php

namespace App\Http\Controllers;

use App\Models\ApiClient;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class ApiIntegrationController extends Controller
{
    public function index(): View
    {
        $clients = ApiClient::query()->where('tenant_id', app(TenantContext::class)->requireId())->latest()->get();

        return view('settings.api-integrations', compact('clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'in:'.implode(',', ApiClient::ABILITIES)],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);
        $token = 'church_'.Str::random(64);
        ApiClient::query()->create([
            'tenant_id' => app(TenantContext::class)->requireId(),
            'name' => $validated['name'],
            'token_hash' => hash('sha256', $token),
            'abilities' => array_values(array_unique($validated['abilities'])),
            'expires_at' => $validated['expires_at'] ?: null,
        ]);

        return redirect()->route('settings.api-integrations')
            ->with('new_api_token', $token)
            ->with('toast', ['type' => 'success', 'message' => 'Jeton créé. Copiez-le maintenant : il ne sera plus affiché.']);
    }

    public function destroy(ApiClient $apiClient): RedirectResponse
    {
        abort_unless($apiClient->tenant_id === app(TenantContext::class)->requireId(), 404);
        $apiClient->update(['revoked_at' => now()]);

        return redirect()->route('settings.api-integrations')
            ->with('toast', ['type' => 'success', 'message' => 'Accès API révoqué immédiatement.']);
    }
}
