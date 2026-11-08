<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Correspondences\Form;
use App\Mail\TenantAnnouncement;
use App\Models\Correspondence;
use App\Models\Member;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\PlatformReminderNotification;
use App\Services\CorrespondenceNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class CorrespondenceEmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_personal_correspondence_is_notified_immediately_to_its_account_holder(): void
    {
        Notification::fake();
        $secretary = User::factory()->role(UserRole::Secretaire)->create();
        $member = Member::factory()->active()->create(['email' => 'destinataire@example.test']);
        $recipient = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);

        Livewire::actingAs($secretary)->test(Form::class)
            ->set('sender_name', 'Secrétariat')
            ->set('subject', 'Convocation de répétition')
            ->set('recipient_member_id', (string) $member->id)
            ->call('save')
            ->assertHasNoErrors();

        Notification::assertSentTo($recipient, PlatformReminderNotification::class, function (PlatformReminderNotification $notification) use ($recipient): bool {
            return $notification->via($recipient) === ['database', 'mail', WebPushChannel::class];
        });
    }

    public function test_an_addressed_member_without_an_account_still_receives_an_email(): void
    {
        Mail::fake();
        config(['mail.delivery_enabled' => true]);
        $member = Member::factory()->active()->create(['email' => 'sans-compte@example.test']);
        $correspondence = Correspondence::factory()->create([
            'subject' => 'Information individuelle',
            'recipient_member_id' => $member->id,
        ]);

        app(CorrespondenceNotificationService::class)->notify($correspondence);

        Mail::assertSent(TenantAnnouncement::class, fn (TenantAnnouncement $mail) => $mail->hasTo('sans-compte@example.test'));
    }
}
