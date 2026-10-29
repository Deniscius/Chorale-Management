<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_recovers_only_their_unread_notifications_after_login(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $notification = new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['title' => 'Répétition', 'message' => 'Bonjour '.$notifiable->name, 'url' => '/repetitions'];
            }
        };
        $user->notify($notification);
        $other->notify($notification);

        $response = $this->actingAs($user)->getJson(route('notifications.index'));

        $response->assertOk()->assertJsonPath('unread', 1)->assertJsonCount(1, 'items')->assertJsonPath('items.0.message', 'Bonjour '.$user->name);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $owner->notify(new class extends Notification
        {
            public function via(object $notifiable): array
            {
                return ['database'];
            }

            public function toDatabase(object $notifiable): array
            {
                return ['title' => 'Privé'];
            }
        });

        $this->actingAs($attacker)->postJson(route('notifications.read', $owner->notifications()->first()))->assertNotFound();
        $this->assertNull($owner->notifications()->first()->read_at);
    }

    public function test_push_subscription_is_validated_scoped_and_encrypted_at_rest(): void
    {
        config(['webpush.public_key' => 'public-test-key']);
        $user = User::factory()->create();
        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/device-test',
            'keys' => ['p256dh' => 'public-device-key', 'auth' => 'auth-device-key'],
            'contentEncoding' => 'aes128gcm',
        ];

        $this->actingAs($user)->postJson(route('notifications.push.store'), $payload)->assertCreated();

        $subscription = PushSubscription::firstOrFail();
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame($payload['endpoint'], $subscription->endpoint);
        $this->assertNotSame($payload['endpoint'], $subscription->getRawOriginal('endpoint'));
        $this->assertSame(hash('sha256', $payload['endpoint']), $subscription->endpoint_hash);
        $this->postJson(route('notifications.push.store'), [...$payload, 'endpoint' => 'https://example.com/push'])->assertUnprocessable();
    }
}
