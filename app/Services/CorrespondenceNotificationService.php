<?php

namespace App\Services;

use App\Mail\TenantAnnouncement;
use App\Models\Correspondence;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PlatformReminderNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/** Delivers a correspondence notification immediately after its transaction. */
final class CorrespondenceNotificationService
{
    public function notify(Correspondence $correspondence): void
    {
        $correspondence->loadMissing(['recipientMember.user', 'assignedMember.user']);

        $members = collect([$correspondence->recipientMember, $correspondence->assignedMember])
            ->filter()
            ->unique('id')
            ->values();

        foreach ($members as $member) {
            $this->notifyMember($member, $correspondence);
        }
    }

    private function notifyMember(Member $member, Correspondence $correspondence): void
    {
        $title = $correspondence->recipient_member_id === $member->id
            ? 'Un courrier vous est adressé'
            : 'Un courrier vous est confié';
        $message = 'Le courrier « '.$correspondence->subject.' » (réf. '.$correspondence->reference.') '.($correspondence->recipient_member_id === $member->id ? 'vous est adressé.' : 'vous a été confié.');

        try {
            if ($user = $member->user) {
                $user->notify(new PlatformReminderNotification(
                    $title,
                    $message,
                    route('personal-notebook.show', absolute: false),
                    'correspondence',
                    'correspondence:'.$correspondence->id.':'.$member->id.':'.$correspondence->updated_at?->getTimestamp(),
                    'mail',
                ));

                return;
            }

            if (config('mail.delivery_enabled') && $member->canReceiveEmail()) {
                $tenant = Tenant::findOrFail($correspondence->tenant_id);
                Mail::to($member->email)->send(new TenantAnnouncement(
                    $title,
                    $message,
                    $tenant->name,
                    route('tenant.entry', ['slug' => $tenant->slug]),
                    'Ouvrir la chorale',
                ));
            }
        } catch (\Throwable $exception) {
            Log::warning('Correspondence notification delivery failed.', [
                'tenant_id' => $correspondence->tenant_id,
                'correspondence_id' => $correspondence->id,
                'member_id' => $member->id,
                'exception' => $exception::class,
            ]);
            report($exception);
        }
    }
}
