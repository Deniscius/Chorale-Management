<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Communications\Compose;
use App\Models\ChoirCommission;
use App\Models\Member;
use App\Models\User;
use App\Mail\TenantAnnouncement;
use App\Notifications\PlatformReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class GroupCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_associate_can_send_to_selected_members_and_signature_is_added(): void
    {
        Notification::fake();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $commission = ChoirCommission::create(['name' => 'Vie culturelle', 'name_key' => 'vie culturelle', 'mission' => 'Animer', 'kind' => 'permanente', 'starts_on' => today(), 'module_key' => 'activites', 'leader_id' => User::factory()->create()->id, 'associate_id' => $associate->id]);
        $member = Member::factory()->create();
        $recipient = User::factory()->create(['member_id' => $member->id]);
        $commission->members()->attach($member->id);

        Livewire::actingAs($associate)->test(Compose::class)
            ->set('commissionId', (string) $commission->id)->set('recipientMode', 'selected')->set('memberIds', [$member->id])
            ->set('title', 'Réunion')->set('message', 'Rendez-vous samedi.')
            ->call('send')->assertHasNoErrors();

        Notification::assertSentTo($recipient, PlatformReminderNotification::class);
        $this->assertDatabaseHas('audit_events', ['actor_id' => $associate->id, 'action' => 'created']);
    }

    public function test_group_message_is_emailed_to_a_selected_member_without_an_account(): void
    {
        Mail::fake();
        config(['mail.delivery_enabled' => true]);
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $commission = ChoirCommission::create(['name' => 'Vie culturelle', 'name_key' => 'vie-culturelle-sans-compte', 'mission' => 'Animer', 'kind' => 'permanente', 'starts_on' => today(), 'module_key' => 'activites', 'leader_id' => User::factory()->create()->id, 'associate_id' => $associate->id]);
        $member = Member::factory()->active()->create(['email' => 'groupe-sans-compte@example.test']);
        $commission->members()->attach($member->id);

        Livewire::actingAs($associate)->test(Compose::class)
            ->set('commissionId', (string) $commission->id)->set('recipientMode', 'selected')->set('memberIds', [$member->id])
            ->set('title', 'Réunion')->set('message', 'Rendez-vous samedi.')
            ->call('send')->assertHasNoErrors();

        Mail::assertSent(TenantAnnouncement::class, fn (TenantAnnouncement $mail) => $mail->hasTo('groupe-sans-compte@example.test'));
    }
}
