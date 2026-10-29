<?php

namespace App\Notifications;

use App\Models\Member;
use App\Models\Rehearsal;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class RehearsalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Rehearsal $rehearsal,
        private readonly Member $member,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Rappel de répétition',
            'message' => $this->personalizedMessage(),
            'url' => route('rehearsals.index', absolute: false),
            'icon' => 'calendar',
            'kind' => 'rehearsal-reminder',
        ];
    }

    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => 'Rappel de votre chorale',
            'body' => $this->personalizedMessage(),
            'url' => route('rehearsals.index', absolute: false),
            'tag' => 'rehearsal-'.$this->rehearsal->id,
            'icon' => '/brand-icon.svg',
        ];
    }

    private function personalizedMessage(): string
    {
        $name = $this->member->first_name ?: $this->member->full_name;

        return Str::limit('Bonjour '.$name.', '.lcfirst(trim(preg_replace('/\s+/', ' ', $this->message))), 240);
    }
}
