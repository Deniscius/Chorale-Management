<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

final class WebPushSender
{
    public function configured(): bool
    {
        return filled(config('webpush.public_key')) && filled(config('webpush.private_key')) && filled(config('webpush.subject'));
    }

    public function send(object $user, array $payload): void
    {
        if (! $this->configured() || ! method_exists($user, 'pushSubscriptions')) {
            return;
        }

        $webPush = new WebPush(['VAPID' => [
            'subject' => (string) config('webpush.subject'),
            'publicKey' => (string) config('webpush.public_key'),
            'privateKey' => (string) config('webpush.private_key'),
        ]]);

        foreach ($user->pushSubscriptions()->get() as $stored) {
            try {
                $report = $webPush->sendOneNotification(new Subscription(
                    $stored->endpoint,
                    $stored->public_key,
                    $stored->auth_token,
                    $stored->content_encoding,
                ), json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), [
                    'TTL' => max(60, (int) config('webpush.ttl', 86400)),
                    'urgency' => 'normal',
                    'topic' => substr(hash('sha256', (string) ($payload['tag'] ?? 'chorale')), 0, 32),
                ]);

                if ($report->isSuccess()) {
                    $stored->forceFill(['last_success_at' => now(), 'last_failure_at' => null])->save();
                } elseif ($report->isSubscriptionExpired()) {
                    $stored->delete();
                } else {
                    $stored->forceFill(['last_failure_at' => now()])->save();
                    Log::warning('Échec Web Push', ['subscription_id' => $stored->id, 'reason' => mb_substr($report->getReason(), 0, 300)]);
                }
            } catch (Throwable $e) {
                $stored->forceFill(['last_failure_at' => now()])->save();
                report($e);
            }
        }
    }
}
