<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Reminder;
use App\Services\ReminderDispatcher;
use App\Services\Sms\D7SmsSender;
use App\Services\Sms\DeliveryRejected;
use App\Services\Sms\SmsSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class D7SmsSenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config([
            'chorale.reminders.sms_driver' => 'd7',
            'chorale.reminders.sms_enabled' => true,
            'chorale.reminders.queued' => false,
            'chorale.sms.d7.token' => 'test-only-d7-token',
            'chorale.sms.d7.originator' => 'CHORALE',
        ]);
    }

    public function test_native_request_normalizes_international_numbers_and_supports_unicode(): void
    {
        Http::fake(function ($request, $options) {
            $this->assertFalse($options['allow_redirects']);

            return Http::response(['status' => 'accepted', 'request_id' => 'test-request']);
        });
        $this->assertInstanceOf(D7SmsSender::class, app(SmsSender::class));
        $this->assertTrue(app(SmsSender::class)->send('00228 90 00 00 00', 'Répétition à l’église 🎶'));
        Http::assertSent(fn ($request) => $request->url() === D7SmsSender::ENDPOINT
            && $request->hasHeader('Authorization', 'Bearer test-only-d7-token')
            && $request->data() === [
                'messages' => [[
                    'channel' => 'sms', 'recipients' => ['+22890000000'],
                    'content' => 'Répétition à l’église 🎶', 'msg_type' => 'text', 'data_coding' => 'auto',
                ]],
                'message_globals' => ['originator' => 'CHORALE'],
            ]);
        Http::assertSentCount(1);
    }

    public function test_missing_configuration_rejects_without_contacting_the_provider(): void
    {
        Http::fake();
        config(['chorale.sms.d7.token' => '']);
        try {
            app(SmsSender::class)->send('+22890000000', 'Test');
            $this->fail('Missing token was accepted.');
        } catch (DeliveryRejected) {
            Http::assertNothingSent();
        }
    }

    #[DataProvider('invalidNumbers')]
    public function test_local_or_malformed_numbers_are_not_guessed(string $number): void
    {
        Http::fake();
        try {
            app(SmsSender::class)->send($number, 'Test');
            $this->fail('Invalid number was accepted.');
        } catch (DeliveryRejected) {
            Http::assertNothingSent();
        }
    }

    public static function invalidNumbers(): array
    {
        return [['90000000'], ['+0123456789'], ['+228abc'], [''], ['+1234567890123456']];
    }

    #[DataProvider('httpStatuses')]
    public function test_refusals_and_ambiguous_responses_are_distinguished(int $status, bool $refused): void
    {
        Http::fake([D7SmsSender::ENDPOINT => Http::response(['detail' => 'private provider response'], $status)]);
        try {
            app(SmsSender::class)->send('+22890000000', 'Test');
            $this->fail('Failure was accepted.');
        } catch (RuntimeException $e) {
            $this->assertSame($refused, $e instanceof DeliveryRejected);
            $this->assertStringNotContainsString('private provider response', $e->getMessage());
        }
        Http::assertSentCount(1);
    }

    public static function httpStatuses(): array
    {
        return [[401, true], [402, true], [422, true], [429, true], [408, false], [500, false], [302, false]];
    }

    #[DataProvider('acknowledgements')]
    public function test_http_success_alone_does_not_mean_the_sms_was_accepted(array $body, bool $refused): void
    {
        Http::fake([D7SmsSender::ENDPOINT => Http::response($body)]);
        try {
            app(SmsSender::class)->send('+22890000000', 'Test');
            $this->fail('Invalid acknowledgement was accepted.');
        } catch (RuntimeException $e) {
            $this->assertSame($refused, $e instanceof DeliveryRejected);
        }
    }

    public static function acknowledgements(): array
    {
        return [[[], false], [['status' => 'accepted'], false], [['status' => 'accepted', 'request_id' => []], false], [['status' => 'accepted', 'request_id' => "unsafe\nreference"], false], [['status' => 'rejected'], true]];
    }

    public function test_rehearsal_reminders_use_d7_once_and_respect_member_consent(): void
    {
        Http::fake([D7SmsSender::ENDPOINT => Http::response(['status' => 'accepted', 'request_id' => 'test-request'])]);
        $rehearsal = Rehearsal::factory()->upcoming()->create();
        Member::factory()->active()->create(['phone' => '+22890000000', 'notify_email' => false, 'notify_sms' => true]);
        Member::factory()->active()->create(['phone' => '+22891000000', 'notify_email' => false, 'notify_sms' => false]);
        $dispatcher = app(ReminderDispatcher::class);
        $dispatcher->dispatchForRehearsal($rehearsal);
        $dispatcher->dispatchForRehearsal($rehearsal);
        Http::assertSentCount(1);
        $this->assertSame('envoye', Reminder::sole()->status->value);
    }

    public function test_transport_failure_is_redacted_and_never_automatically_retried(): void
    {
        $calls = 0;
        Http::fake(function () use (&$calls) {
            $calls++;
            throw new ConnectionException('secret-token recipient message-content');
        });
        $rehearsal = Rehearsal::factory()->upcoming()->create();
        Member::factory()->active()->create(['phone' => '+22890000000', 'notify_email' => false, 'notify_sms' => true]);
        $dispatcher = app(ReminderDispatcher::class);
        $dispatcher->dispatchForRehearsal($rehearsal);
        $entry = Reminder::sole();
        $this->assertSame('incertain', $entry->status->value);
        $this->assertStringNotContainsString('secret-token', $entry->error);
        $this->assertStringNotContainsString('recipient', $entry->error);
        $dispatcher->dispatchForRehearsal($rehearsal);
        $this->assertSame(1, $entry->fresh()->attempts);
        $this->assertSame(1, $calls);
    }

    public function test_disabling_sms_prevents_all_d7_requests(): void
    {
        Http::fake();
        config(['chorale.reminders.sms_enabled' => false]);
        $rehearsal = Rehearsal::factory()->upcoming()->create();
        Member::factory()->active()->create(['phone' => '+22890000000', 'notify_email' => false, 'notify_sms' => true]);
        app(ReminderDispatcher::class)->dispatchForRehearsal($rehearsal);
        Http::assertNothingSent();
        $this->assertSame(0, Reminder::count());
    }
}
