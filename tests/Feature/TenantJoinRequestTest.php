<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Onboarding\Index;
use App\Mail\JoinRequestReceived;
use App\Mail\TenantAnnouncement;
use App\Models\Member;
use App\Models\MemberJoinRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class TenantJoinRequestTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::query()->firstOrFail();
    }

    public function test_public_tenant_link_offers_a_secure_join_request(): void
    {
        $tenant = $this->tenant();

        $this->get(route('tenant.entry', $tenant->slug))
            ->assertOk()
            ->assertSee('Demander à rejoindre');

        $this->get(route('tenant.join.create', $tenant->slug))
            ->assertOk()
            ->assertSee('Demander à rejoindre la chorale');
    }

    public function test_join_request_is_scoped_to_the_tenant_and_acknowledged_by_email(): void
    {
        config()->set('mail.delivery_enabled', true);
        Mail::fake();
        $tenant = $this->tenant();
        User::factory()->role(UserRole::Admin)->create(['email' => 'responsable@example.test']);

        $this->post(route('tenant.join.store', $tenant->slug), [
            'first_name' => 'Afi', 'last_name' => 'Kossi', 'email' => 'afi@example.test',
            'phone' => '+228 90 00 00 00', 'voice' => 'soprano',
        ])->assertRedirect(route('tenant.entry', $tenant->slug));

        $this->assertDatabaseHas('member_join_requests', [
            'tenant_id' => $tenant->id, 'email' => 'afi@example.test', 'status' => 'pending',
        ]);
        Mail::assertSent(JoinRequestReceived::class, fn (JoinRequestReceived $mail) => $mail->hasTo('afi@example.test'));
        Mail::assertSent(TenantAnnouncement::class, fn (TenantAnnouncement $mail) => $mail->hasTo('responsable@example.test'));
    }

    public function test_duplicate_pending_request_is_rejected_without_revealing_membership(): void
    {
        $tenant = $this->tenant();
        app(TenantContext::class)->run($tenant->id, fn () => MemberJoinRequest::create([
            'first_name' => 'Afi', 'last_name' => 'Kossi', 'email' => 'afi@example.test',
            'email_verification_hash' => hash('sha256', Str::random(64)), 'email_verification_expires_at' => now()->addDay(),
        ]));

        $this->from(route('tenant.join.create', $tenant->slug))
            ->post(route('tenant.join.store', $tenant->slug), [
                'first_name' => 'Afi', 'last_name' => 'Kossi', 'email' => 'afi@example.test',
            ])
            ->assertRedirect(route('tenant.join.create', $tenant->slug))
            ->assertSessionHasErrors('email');
    }

    public function test_admin_approval_creates_an_invited_member_and_sends_activation(): void
    {
        config()->set('mail.delivery_enabled', true);
        Mail::fake();
        $tenant = $this->tenant();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $request = app(TenantContext::class)->run($tenant->id, fn () => MemberJoinRequest::create([
            'first_name' => 'Afi', 'last_name' => 'Kossi', 'email' => 'afi@example.test', 'voice' => 'soprano',
            'email_verification_hash' => hash('sha256', Str::random(64)), 'email_verification_expires_at' => now()->addDay(), 'email_verified_at' => now(),
        ]));

        Livewire::actingAs($admin)->test(Index::class)
            ->call('approveJoinRequest', $request->id)
            ->assertHasNoErrors();

        $member = Member::query()->where('email', 'afi@example.test')->firstOrFail();
        $this->assertSame('invite', $member->status->value);
        $this->assertDatabaseHas('member_join_requests', ['id' => $request->id, 'status' => 'approved', 'member_id' => $member->id]);
        Mail::assertSent(TenantAnnouncement::class, fn ($mail) => $mail->hasTo('afi@example.test'));
        $this->assertDatabaseCount('member_invitations', 1);
    }

    public function test_admin_can_approve_without_an_extra_pre_activation_email_step(): void
    {
        $tenant = $this->tenant();
        $admin = User::factory()->role(UserRole::Admin)->create();
        $request = app(TenantContext::class)->run($tenant->id, fn () => MemberJoinRequest::create([
            'first_name' => 'Kossi', 'last_name' => 'Afi', 'email' => 'kossi@example.test',
            'email_verification_hash' => hash('sha256', Str::random(64)), 'email_verification_expires_at' => now()->addDay(),
        ]));

        Livewire::actingAs($admin)->test(Index::class)->call('approveJoinRequest', $request->id)->assertHasNoErrors();
        $this->assertDatabaseHas('member_join_requests', ['id' => $request->id, 'status' => 'approved']);
    }
}
