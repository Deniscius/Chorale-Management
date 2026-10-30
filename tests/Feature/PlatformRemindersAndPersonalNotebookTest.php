<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\AttendanceIncident;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\NotificationDispatch;
use App\Models\Performance;
use App\Models\Rehearsal;
use App\Models\User;
use App\Notifications\PlatformReminderNotification;
use App\Services\PersonalNotebook;
use App\Services\PlatformReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class PlatformRemindersAndPersonalNotebookTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_reminders_are_personalized_targeted_and_idempotent(): void
    {
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
