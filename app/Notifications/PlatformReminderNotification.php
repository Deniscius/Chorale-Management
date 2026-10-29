<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

final class PlatformReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly string $message,
        private readonly string $url,
        private readonly string $kind,
        private readonly string $tag,
        private readonly string $icon = 'calendar',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->personalizedMessage($notifiable),
            'url' => $this->safeUrl(),
            'icon' => $this->icon,
            'kind' => $this->kind,
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->personalizedMessage($notifiable),
            'url' => $this->safeUrl(),
            'tag' => $this->tag,
            'icon' => '/brand-icon.svg',
        ];
    }

    private function personalizedMessage(object $notifiable): string
    {
        $name = $notifiable->member?->first_name ?: Str::before((string) $notifiable->name, ' ');

        return Str::limit('Bonjour '.$name.', '.lcfirst(trim(preg_replace('/\s+/', ' ', $this->message))), 240);
    }

    private function safeUrl(): string
    {
        return str_starts_with($this->url, '/') && ! str_starts_with($this->url, '//')
            ? $this->url
            : route('personal-notebook.show', absolute: false);
    }
}
