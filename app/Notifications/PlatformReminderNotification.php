<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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
        $channels = ['database', WebPushChannel::class];

        // Le choix appartient au choriste : un compte ne reçoit jamais de
        // courriel si sa fiche ne contient pas une adresse et l'accord e-mail.
        if (config('mail.delivery_enabled') && $notifiable->member?->canReceiveEmail()) {
            $channels[] = 'mail';
        }

        return $channels;
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

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title.' — Chorale Management')
            ->greeting('Bonjour '.($notifiable->member?->first_name ?: Str::before((string) $notifiable->name, ' ')).',')
            ->line($this->personalizedMessage($notifiable))
            ->action('Ouvrir mon carnet', url($this->safeUrl()))
            ->line('Vous recevez ce rappel car les notifications e-mail sont activées dans votre fiche choriste.');
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
