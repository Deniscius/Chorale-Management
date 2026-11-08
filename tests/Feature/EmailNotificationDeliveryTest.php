<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Communications\Compose;
use App\Livewire\Settings\EmailDelivery;
use App\Mail\MailDeliveryRejected;
use App\Mail\RehearsalReminder;
use App\Models\Member;
use App\Models\NotificationDispatch;
use App\Models\Performance;
use App\Models\Rehearsal;
use App\Models\User;
use App\Notifications\PlatformReminderNotification;
use App\Services\MailConfiguration;
use App\Services\PlatformReminderDispatcher;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class EmailNotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'brevo', 'mail.delivery_enabled' => true,
            'mail.mailers.brevo.key' => 'test-only-private-key', 'mail.from.address' => 'notifications@example.test',
            'chorale.reminders.enabled' => true, 'chorale.reminders.platform_days' => [7],
            'webpush.public_key' => null, 'webpush.private_key' => null]);
        Http::preventStrayRequests();
    }

    private function accepted(): void
    {
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['messageId' => '<accepted-test>'], 201)]);
    }

    private function recipient(): User
    {
        $member = Member::factory()->active()->create(['email' => 'contact@example.test']);

        return User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id, 'email' => 'login@example.test']);
    }

    public function test_business_email_uses_the_contact_address_and_keeps_full_message_and_signature(): void
    {
        $this->accepted();
        $user = $this->recipient();
        $message = str_repeat('Information de la chorale. ', 30).'FIN DU MESSAGE — Signature du bureau';
        $user->notify(new PlatformReminderNotification('Convocation', $message, '/mon-carnet', 'activity', 'test-full-message'));

        Http::assertSent(fn ($request) => $request['to'][0]['email'] === 'contact@example.test'
            && str_contains($request['htmlContent'], 'FIN DU MESSAGE')
            && str_contains($request['htmlContent'], 'Signature du bureau'));
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame('login@example.test', $user->routeNotificationForMail(new VerifyEmail));
        $this->assertSame('login@example.test', $user->routeNotificationForMail(new ResetPassword('test-token')));
    }

    public function test_staff_without_a_member_profile_and_members_without_contact_email_use_their_account(): void
    {
        $this->accepted();
        $staff = User::factory()->role(UserRole::Directeur)->create(['member_id' => null, 'email' => 'responsable@example.test']);
        $staff->notify(new PlatformReminderNotification('Courrier à traiter', 'Information du bureau.', '/mon-carnet', 'correspondence', 'staff-test'));
        Http::assertSent(fn ($request) => $request['to'][0]['email'] === 'responsable@example.test');
        $user = $this->recipient();
        $user->member()->update(['email' => null]);
        $this->assertSame('login@example.test', $user->fresh()->notificationEmail());
    }

    public function test_disabled_mail_can_be_sent_later_without_repeating_the_application_copy(): void
    {
        $this->accepted();
        $user = $this->recipient();
        Performance::factory()->create(['scheduled_at' => now()->addDays(7), 'status' => 'planifiee']);
        config(['mail.delivery_enabled' => false]);
        app(PlatformReminderDispatcher::class)->dispatchAll();
        $dispatch = NotificationDispatch::query()->sole();
        $this->assertNotNull($dispatch->notified_at);
        $this->assertSame('pending', $dispatch->mail_status);
        Http::assertNothingSent();
        config(['mail.delivery_enabled' => true]);
        app(PlatformReminderDispatcher::class)->dispatchAll();
        app(PlatformReminderDispatcher::class)->dispatchAll();
        $this->assertSame('accepted', $dispatch->fresh()->mail_status);
        $this->assertNotNull($dispatch->fresh()->mail_notified_at);
        $this->assertDatabaseCount('notifications', 1);
        Http::assertSentCount(1);
    }

    public function test_a_provider_rejection_retries_mail_without_duplicating_the_application_notification(): void
    {
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::sequence()
            ->push(['code' => 'unauthorized'], 401)->push(['messageId' => '<retry-ok>'], 201)]);
        $this->recipient();
        Performance::factory()->create(['scheduled_at' => now()->addDays(7), 'status' => 'planifiee']);
        app(PlatformReminderDispatcher::class)->dispatchAll();
        $dispatch = NotificationDispatch::query()->sole();
        $this->assertSame('failed', $dispatch->mail_status);
        $this->assertNotNull($dispatch->notified_at);
        $this->assertStringContainsString('401', $dispatch->error);
        app(PlatformReminderDispatcher::class)->dispatchAll();
        app(PlatformReminderDispatcher::class)->dispatchAll();
        $this->assertSame('accepted', $dispatch->fresh()->mail_status);
        $this->assertSame(2, $dispatch->fresh()->attempts);
        $this->assertDatabaseCount('notifications', 1);
        Http::assertSentCount(2);
    }

    public function test_an_uncertain_provider_response_is_not_blindly_retried(): void
    {
        $requests = 0;
        Http::fake(function () use (&$requests) {
            $requests++;
            throw new ConnectionException('Test-only simulated timeout');
        });
        $this->recipient();
        Performance::factory()->create(['scheduled_at' => now()->addDays(7), 'status' => 'planifiee']);
        app(PlatformReminderDispatcher::class)->dispatchAll();
        app(PlatformReminderDispatcher::class)->dispatchAll();
        $this->assertSame('uncertain', NotificationDispatch::query()->sole()->mail_status);
        $this->assertSame(1, $requests);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_delayed_imminent_reminder_runs_once_in_its_quarter_hour(): void
    {
        Notification::fake();
        config(['chorale.reminders.platform_days' => []]);
        $user = $this->recipient();
        Performance::factory()->create(['scheduled_at' => Carbon::parse('2026-11-08 18:00:00'), 'status' => 'planifiee']);
        $this->travelTo(Carbon::parse('2026-11-08 17:05:00'));
        app(PlatformReminderDispatcher::class)->dispatchAll();
        $this->travelTo(Carbon::parse('2026-11-08 17:10:00'));
        app(PlatformReminderDispatcher::class)->dispatchAll();
        Notification::assertSentToTimes($user, PlatformReminderNotification::class, 1);
        $this->travelBack();
    }

    public function test_the_remote_scheduler_can_deliver_without_a_separate_queue_worker(): void
    {
        Mail::fake();
        config(['chorale.reminders.queued' => true, 'queue.default' => 'database']);
        Member::factory()->active()->create(['email' => 'repetition@example.test']);
        Rehearsal::factory()->create(['date' => today()->addDays(2), 'status' => 'planifiee']);
        $this->artisan('chorale:send-reminders', ['--inline' => true, '--rehearsals-only' => true])->assertExitCode(0);
        Mail::assertSent(RehearsalReminder::class);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertTrue(config('chorale.reminders.queued'));
    }

    public function test_group_delivery_does_not_report_a_failed_email_as_successful(): void
    {
        Http::fake(['api.brevo.com/v3/smtp/email' => Http::response(['code' => 'unauthorized'], 401)]);
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->recipient();
        Livewire::actingAs($admin)->test(Compose::class)->set('audience', 'chorale')
            ->set('title', 'Message important')->set('message', 'Ne pas perdre ce texte en cas d’échec.')
            ->call('send')->assertSet('title', 'Message important')
            ->assertDispatched('toast', fn ($event, $params) => $params['type'] === 'error' && str_contains($params['message'], '0 e-mail(s)'));
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_diagnostics_hide_credentials_and_send_one_test_only_to_the_signed_in_admin(): void
    {
        $this->accepted();
        $admin = User::factory()->role(UserRole::Admin)->create(['email' => 'admin@example.test']);
        $this->actingAs($admin)->get(route('settings.email-delivery'))->assertOk()
            ->assertSee('brevo')->assertDontSee('test-only-private-key');
        $component = Livewire::actingAs($admin)->test(EmailDelivery::class)->call('sendTest')->assertSet('resultType', 'success');
        Http::assertSent(fn ($request) => $request['to'][0]['email'] === 'admin@example.test');
        $component->call('sendTest')->assertSet('resultType', 'error');
        Http::assertSentCount(1);
        $chorister = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($chorister)->get(route('settings.email-delivery'))->assertForbidden();
    }

    public function test_a_disabled_or_simulated_transport_fails_readiness_without_exposing_secrets(): void
    {
        config(['mail.default' => 'log']);
        try {
            app(MailConfiguration::class)->assertReady();
            $this->fail('Le transport de simulation ne doit pas passer le contrôle.');
        } catch (MailDeliveryRejected $exception) {
            $this->assertStringContainsString('MAIL_MAILER', $exception->getMessage());
            $this->assertStringNotContainsString('test-only-private-key', $exception->getMessage());
        }
        $this->artisan('chorale:check-mail')->assertExitCode(1);
        config(['mail.default' => 'brevo']);
        $this->artisan('chorale:check-mail')->assertExitCode(0);
    }
}
