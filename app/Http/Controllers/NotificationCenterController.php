<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationCenterController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = $user->notifications()->latest()->limit(20)->get()->map(fn ($notification) => [
            'id' => $notification->id,
            'title' => $notification->data['title'] ?? 'Information',
            'message' => $notification->data['message'] ?? '',
            'url' => $notification->data['url'] ?? route('dashboard', absolute: false),
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->diffForHumans(),
        ]);

        return response()->json([
            'items' => $items,
            'unread' => $user->unreadNotifications()->count(),
            'push' => [
                'available' => filled(config('webpush.public_key')),
                'publicKey' => config('webpush.public_key'),
                'subscribed' => $user->pushSubscriptions()->exists(),
            ],
        ])->header('Cache-Control', 'private, no-store');
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
