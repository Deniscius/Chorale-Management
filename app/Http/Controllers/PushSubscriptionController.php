<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(filled(config('webpush.public_key')), 503, 'Les notifications sur cet appareil ne sont pas encore configurées.');
        $data = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
                $allowed = ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'notify.windows.com', 'push.apple.com'];
                $trusted = collect($allowed)->contains(fn (string $suffix) => $host === $suffix || str_ends_with($host, '.'.$suffix));
                if (! $trusted || parse_url((string) $value, PHP_URL_USER) !== null) {
                    $fail('Le fournisseur de notifications de cet appareil n’est pas reconnu.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);
        $hash = hash('sha256', $data['endpoint']);

        DB::transaction(function () use ($request, $data, $hash): void {
            DB::table('push_subscriptions')->where('endpoint_hash', $hash)->delete();
            PushSubscription::create([
                'user_id' => $request->user()->id,
                'endpoint_hash' => $hash,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['contentEncoding'] ?? 'aes128gcm',
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            ]);
        }, 3);

        return response()->json(['ok' => true], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url:https', 'max:2048']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();

        return response()->json(['ok' => true]);
    }
}
