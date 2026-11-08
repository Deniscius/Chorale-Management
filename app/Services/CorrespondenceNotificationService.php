<?php

namespace App\Services;

use App\Mail\TenantAnnouncement;
use App\Models\Correspondence;
use App\Models\Member;
use App\Models\Tenant;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\PlatformReminderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

/** Delivers a correspondence notification immediately after its transaction. */
final class CorrespondenceNotificationService
{
    public function notify(Correspondence $correspondence): array
    {
        $correspondence->loadMissing(['recipientMember.user', 'assignedMember.user']);

        $members = collect([$correspondence->recipientMember, $correspondence->assignedMember])
            ->filter()
            ->unique('id')
            ->values();

        $result = ['application' => 0, 'email' => 0, 'failed' => 0];
        foreach ($members as $member) {
            foreach ($this->notifyMember($member, $correspondence) as $channel => $count) {
                $result[$channel] += $count;
            }
        }

        return $result;
    }

    private function notifyMember(Member $member, Correspondence $correspondence): array
    {
        $title = $correspondence->recipient_member_id === $member->id
            ? 'Un courrier vous est adressé'
            : 'Un courrier vous est confié';
        $message = 'Le courrier « '.$correspondence->subject.' » (réf. '.$correspondence->reference.') '.($correspondence->recipient_member_id === $member->id ? 'vous est adressé.' : 'vous a été confié.');

        $result = ['application' => 0, 'email' => 0, 'failed' => 0];
        $notification = new PlatformReminderNotification(
            $title, $message, route('personal-notebook.show', absolute: false), 'correspondence',
            'correspondence:'.$correspondence->id.':'.$member->id.':'.$correspondence->updated_at?->getTimestamp(), 'mail',
        );
        if ($user = $member->user) {
            $user->loadMissing('member');
            try {
                Notification::sendNow($user, $notification, ['database', WebPushChannel::class]);
                $result['application']++;
            } catch (\Throwable $exception) {
                $result['failed']++;
                report($exception);
            }
        }
        if (! config('mail.delivery_enabled')) {
            return $result;
        }
        try {
            if ($user && $user->notificationEmail()) {
                Notification::sendNow($user, $notification, ['mail']);
                $result['email']++;
            } elseif (! $user && $member->canReceiveEmail()) {
                $tenant = Tenant::findOrFail($correspondence->tenant_id);
                Mail::to(trim($member->email))->send(new TenantAnnouncement(
                    $title, $message, $tenant->name, route('tenant.entry', ['slug' => $tenant->slug]), 'Ouvrir la chorale',
                ));
                $result['email']++;
            }
        } catch (\Throwable $exception) {
            $result['failed']++;
            Log::warning('Correspondence email delivery failed.', [
                'tenant_id' => $correspondence->tenant_id, 'correspondence_id' => $correspondence->id,
                'member_id' => $member->id, 'exception' => $exception::class,
            ]);
            report($exception);
        }

        return $result;
    }
}
