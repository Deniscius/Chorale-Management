<?php

namespace App\Notifications\Channels;

use App\Services\WebPushSender;
use Illuminate\Notifications\Notification;

final class WebPushChannel
{
    public function __construct(private readonly WebPushSender $sender) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toWebPush')) {
            return;
        }

        try {
            $this->sender->send($notifiable, $notification->toWebPush($notifiable));
        } catch (\Throwable $exception) {
            // Optional browser delivery must not turn an accepted e-mail into a failure.
            report($exception);
        }
    }
}
