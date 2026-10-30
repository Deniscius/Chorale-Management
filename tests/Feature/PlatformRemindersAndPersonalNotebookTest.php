<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Correspondences\Form as CorrespondenceForm;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\AttendanceIncident;
use App\Models\Contribution;
use App\Models\Correspondence;
use App\Models\Document;
use App\Models\Member;
use App\Models\NotificationDispatch;
use App\Models\Performance;
use App\Models\Rehearsal;
use App\Models\User;
use App\Notifications\PlatformReminderNotification;
use App\Services\PersonalNotebook;
use App\Services\PlatformReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class PlatformRemindersAndPersonalNotebookTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_reminders_are_personalized_targeted_and_idempotent(): void
    {
        Carbon::setTestNow('2026-10-30 08:00:00');
        Notification::fake();
        $alice = Member::factory()->active()->create(['first_name' => 'Alice']);
        $bruno = Member::factory()->active()->create(['first_name' => 'Bruno']);
        $aliceUser = User::factory()->role(UserRole::Choriste)->create(['member_id' => $alice->id]);
        $brunoUser = User::factory()->role(UserRole::Choriste)->create(['member_id' => $bruno->id]);

        Rehearsal::factory()->create(['date' => today(), 'status' => 'planifiee']);
        Performance::factory()->create(['title' => 'Messe patronale', 'scheduled_at' => now()->addDays(7), 'status' => 'planifiee']);
        $activity = Activity::factory()->create(['title' => 'Formation ciblée', 'starts_at' => now()->addDays(2), 'status' => 'planifiee']);
        ActivityParticipant::query()->create(['activity_id' => $activity->id, 'member_id' => $alice->id, 'status' => 'invite']);

        $first = app(PlatformReminderDispatcher::class)->dispatchAll();
        $second = app(PlatformReminderDispatcher::class)->dispatchAll();

        $this->assertSame(5, $first['sent']);
        $this->assertSame(5, $second['skipped']);
        $this->assertSame(5, NotificationDispatch::count());
        Notification::assertSentToTimes($aliceUser, PlatformReminderNotification::class, 3);
        Notification::assertSentToTimes($brunoUser, PlatformReminderNotification::class, 2);
        Carbon::setTestNow();
    }

    public function test_personal_obligations_notify_only_the_concerned_chorister(): void
    {
        Notification::fake();
        $member = Member::factory()->active()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $other = User::factory()->create(['member_id' => Member::factory()->active()->create()->id]);
        Contribution::factory()->create([
            'member_id' => $member->id, 'year' => today()->year, 'month' => today()->month,
            'due_amount' => 5000, 'paid_amount' => 1000, 'status' => 'partiel',
        ]);
        AttendanceIncident::query()->create([
            'member_id' => $member->id, 'source_type' => 'rehearsal', 'occurred_on' => today(),
            'event_label' => 'Répétition générale', 'incident_type' => 'late', 'minutes_late' => 10,
            'amount' => 500, 'status' => 'pending',
        ]);

        app(PlatformReminderDispatcher::class)->dispatchAll();

        Notification::assertSentToTimes($user, PlatformReminderNotification::class, 2);
        Notification::assertNotSentTo($other, PlatformReminderNotification::class);
    }

    public function test_a_failed_application_notification_is_retried_without_repeating_a_success(): void
    {
        Notification::fake();
        $member = Member::factory()->active()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        $performance = Performance::factory()->create(['scheduled_at' => now()->addDays(7), 'status' => 'planifiee']);
        $fingerprint = hash('sha256', "performance:{$performance->id}:{$performance->scheduled_at->toDateString()}:d7");
        $dispatch = NotificationDispatch::query()->create([
            'user_id' => $user->id, 'fingerprint' => $fingerprint, 'kind' => 'performance',
            'subject_type' => Performance::class, 'subject_id' => $performance->id,
            'scheduled_for' => $performance->scheduled_at, 'attempts' => 1, 'error' => 'Échec temporaire',
        ]);

        app(PlatformReminderDispatcher::class)->dispatchAll();
        app(PlatformReminderDispatcher::class)->dispatchAll();

        $this->assertSame(2, $dispatch->fresh()->attempts);
        $this->assertNotNull($dispatch->fresh()->notified_at);
        Notification::assertSentToTimes($user, PlatformReminderNotification::class, 1);
    }

    public function test_day_j_reminders_repeat_once_for_each_configured_time_slot(): void
    {
        Notification::fake();
        config(['chorale.reminders.platform_days' => [0], 'chorale.reminders.day_of_reminder_hours' => [8, 12, 17]]);
        $member = Member::factory()->active()->create();
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        Performance::factory()->create(['scheduled_at' => Carbon::parse('2026-10-30 18:00:00'), 'status' => 'planifiee']);

        Carbon::setTestNow('2026-10-30 08:05:00');
        app(PlatformReminderDispatcher::class)->dispatchAll();
        Carbon::setTestNow('2026-10-30 12:05:00');
        app(PlatformReminderDispatcher::class)->dispatchAll();
        Carbon::setTestNow('2026-10-30 17:05:00');
        app(PlatformReminderDispatcher::class)->dispatchAll();
        app(PlatformReminderDispatcher::class)->dispatchAll();
        Carbon::setTestNow();

        Notification::assertSentToTimes($user, PlatformReminderNotification::class, 3);
        $this->assertSame(3, NotificationDispatch::query()->where('kind', 'performance')->count());
    }

    public function test_personal_notebook_shows_common_and_assigned_events_without_leaking_another_members_activity(): void
    {
        $member = Member::factory()->active()->create(['first_name' => 'Afi', 'photo_path' => 'tenants/1/members/1/identity/afi.png']);
        $other = Member::factory()->active()->create();
        $user = User::factory()->create(['member_id' => $member->id]);
        Rehearsal::factory()->create(['date' => today()->addDay(), 'status' => 'planifiee']);
        Performance::factory()->create(['title' => 'Concert commun', 'scheduled_at' => now()->addDays(3), 'status' => 'planifiee']);
        $mine = Activity::factory()->create(['title' => 'Activité Afi', 'starts_at' => now()->addDays(4), 'status' => 'planifiee']);
        $theirs = Activity::factory()->create(['title' => 'Activité privée autre', 'starts_at' => now()->addDays(5), 'status' => 'planifiee']);
        ActivityParticipant::query()->create(['activity_id' => $mine->id, 'member_id' => $member->id, 'status' => 'invite']);
        ActivityParticipant::query()->create(['activity_id' => $theirs->id, 'member_id' => $other->id, 'status' => 'invite']);

        $notebook = app(PersonalNotebook::class)->for($member);

        $this->assertSame(['Répétition de la chorale', 'Concert commun', 'Activité Afi'], $notebook['timeline']->pluck('title')->all());
        $this->actingAs($user)->get(route('personal-notebook.show'))
            ->assertOk()->assertSee('Carnet personnel de Afi')->assertSee('Activité Afi')
            ->assertSee(route('members.photo', $member), false)->assertDontSee('Activité privée autre');
    }

    public function test_personal_notebook_shows_shared_and_personal_letters_without_leaking_private_letters(): void
    {
        $member = Member::factory()->active()->create(['first_name' => 'Afi']);
        $other = Member::factory()->active()->create(['first_name' => 'Kossi']);
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        Correspondence::factory()->create(['subject' => 'Information pour toute la chorale', 'confidentiality' => 'normal']);
        Correspondence::factory()->create(['subject' => 'Courrier personnel Afi', 'recipient_member_id' => $member->id, 'confidentiality' => 'confidentiel']);
        Correspondence::factory()->create(['subject' => 'Courrier personnel Kossi', 'recipient_member_id' => $other->id, 'confidentiality' => 'confidentiel']);
        Correspondence::factory()->create(['subject' => 'Courrier confidentiel de direction', 'confidentiality' => 'confidentiel']);

        $notebook = app(PersonalNotebook::class)->for($member);

        $this->assertTrue($notebook['correspondences']->contains('subject', 'Information pour toute la chorale'));
        $this->assertTrue($notebook['correspondences']->contains('subject', 'Courrier personnel Afi'));
        $this->assertFalse($notebook['correspondences']->contains('subject', 'Courrier personnel Kossi'));
        $this->assertFalse($notebook['correspondences']->contains('subject', 'Courrier confidentiel de direction'));

        $this->actingAs($user)->get(route('personal-notebook.show'))
            ->assertOk()->assertSee('Courriers de la chorale')->assertSee('Information pour toute la chorale')->assertSee('Courrier personnel Afi')
            ->assertDontSee('Courrier personnel Kossi')->assertDontSee('Courrier confidentiel de direction');
    }

    public function test_personal_notebook_lists_only_documents_allowed_by_visibility(): void
    {
        $member = Member::factory()->active()->create();
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        Document::factory()->create(['title' => 'Programme partagé', 'visibility' => 'published']);
        Document::factory()->create(['title' => 'Archive privée', 'visibility' => 'private']);

        $this->actingAs($user)->get(route('personal-notebook.show'))
            ->assertOk()->assertSee('Archives à consulter')->assertSee('Programme partagé')->assertDontSee('Archive privée');
    }

    public function test_direction_can_address_a_personal_letter_to_one_active_chorister(): void
    {
        $director = User::factory()->role(UserRole::Directeur)->create();
        $member = Member::factory()->active()->create(['first_name' => 'Afi', 'last_name' => 'Koffi']);

        Livewire::actingAs($director)->test(CorrespondenceForm::class)
            ->set('sender_name', 'Direction')
            ->set('subject', 'Convocation personnelle')
            ->set('recipient_member_id', (string) $member->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('correspondences', [
            'recipient_member_id' => $member->id,
            'recipient_name' => 'Afi Koffi',
            'subject' => 'Convocation personnelle',
        ]);
    }

    public function test_personal_notebook_requires_authentication(): void
    {
        $this->get(route('personal-notebook.show'))->assertRedirect(route('login'));
    }

    public function test_personal_notebook_calculates_monthly_and_attendance_arrears_from_membership_date(): void
    {
        $member = Member::factory()->active()->create([
            'first_name' => 'Ama',
            'joined_on' => today()->subMonths(2)->startOfMonth(),
        ]);
        $user = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);

        Contribution::factory()->create([
            'member_id' => $member->id, 'type_code' => 'mensuelle',
            'year' => today()->subMonths(2)->year, 'month' => today()->subMonths(2)->month,
            'due_amount' => 2000, 'paid_amount' => 2000, 'status' => 'payee',
        ]);
        Contribution::factory()->create([
            'member_id' => $member->id, 'type_code' => 'mensuelle',
            'year' => today()->subMonth()->year, 'month' => today()->subMonth()->month,
            'due_amount' => 2000, 'paid_amount' => 500, 'status' => 'partiel',
        ]);
        AttendanceIncident::query()->create([
            'member_id' => $member->id, 'source_type' => 'rehearsal', 'occurred_on' => today(),
            'event_label' => 'Répétition', 'incident_type' => 'absence', 'amount' => 1000, 'status' => 'pending',
        ]);
        AttendanceIncident::query()->create([
            'member_id' => $member->id, 'source_type' => 'activity', 'occurred_on' => today(),
            'event_label' => 'Sortie', 'incident_type' => 'late', 'minutes_late' => 15,
            'amount' => 500, 'status' => 'pending',
        ]);
        AttendanceIncident::query()->create([
            'member_id' => $member->id, 'source_type' => 'activity', 'occurred_on' => today(),
            'event_label' => 'Ancienne sortie', 'incident_type' => 'absence', 'amount' => 9000, 'status' => 'waived',
        ]);

        $notebook = app(PersonalNotebook::class)->for($member);

        $this->assertSame(3, $notebook['monthlyArrears']['expected_months']);
        $this->assertSame(1, $notebook['monthlyArrears']['missing_months']);
        $this->assertSame(3500.0, $notebook['outstanding']);
        $this->assertSame(1500.0, $notebook['incidentAmount']);
        $this->assertSame(1, $notebook['incidentBreakdown']['absence']['count']);
        $this->actingAs($user)->get(route('personal-notebook.show'))
            ->assertOk()
            ->assertSee('rendez-vous dans les 45 prochains jours')
            ->assertDontSee('45.');
    }
}
