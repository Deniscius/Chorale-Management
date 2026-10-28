<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ApiClient;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_rejects_missing_and_unknown_tokens(): void
    {
        $this->getJson('/api/v1/members')->assertUnauthorized();
        $this->withToken('church_'.str_repeat('x', 64))->getJson('/api/v1/members')->assertUnauthorized();
    }

    public function test_scoped_token_reads_only_its_tenant_and_authorized_sections(): void
    {
        config(['tenancy.enabled' => true]);
        $tenant = Tenant::findOrFail(1);
        $token = 'church_'.str_repeat('a', 64);
        app(TenantContext::class)->run($tenant->id, fn () => ApiClient::query()->create([
            'name' => 'Spring', 'token_hash' => hash('sha256', $token), 'abilities' => ['members:read'],
        ]));
        app(TenantContext::class)->run($tenant->id, fn () => Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'API']));

        $foreign = Tenant::unguarded(fn () => Tenant::query()->create([
            'name' => 'Autre', 'slug' => 'autre-api', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDay(),
        ]));
        app(TenantContext::class)->run($foreign->id, fn () => Member::factory()->create(['first_name' => 'Secret', 'last_name' => 'Étranger']));

        $response = $this->withToken($token)->getJson('/api/v1/members')->assertOk()
            ->assertJsonFragment(['full_name' => 'Alice API'])
            ->assertJsonMissing(['full_name' => 'Secret Étranger']);
        $id = $response->json('data.0.id');
        $this->withToken($token)->getJson('/api/v1/members/'.$id)
            ->assertOk()->assertJsonMissingPath('data.finance')->assertJsonMissingPath('data.attendance');
    }

    public function test_finance_and_attendance_are_returned_only_with_explicit_abilities(): void
    {
        config(['tenancy.enabled' => true]);
        $token = 'church_'.str_repeat('b', 64);
        app(TenantContext::class)->run(1, fn () => ApiClient::query()->create([
            'name' => 'Spring complet', 'token_hash' => hash('sha256', $token),
            'abilities' => ['members:read', 'attendance:read', 'finance:read'],
        ]));
        $member = app(TenantContext::class)->run(1, function () {
            $member = Member::factory()->create();
            Contribution::factory()->create(['member_id' => $member->id, 'due_amount' => 5000, 'paid_amount' => 2000, 'status' => 'partiel']);

            return $member;
        });

        $this->withToken($token)->getJson('/api/v1/members/'.$member->id)
            ->assertOk()->assertJsonPath('data.finance.contributions_balance', 3000)
            ->assertJsonPath('data.attendance.present', 0);
    }

    public function test_admin_creates_a_token_once_and_can_revoke_only_own_tenant_client(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin)->post(route('settings.api-integrations.store'), [
            'name' => 'church_project_spring', 'abilities' => ['members:read'],
            'expires_at' => today()->addMonth()->toDateString(),
        ])->assertRedirect(route('settings.api-integrations'))->assertSessionHas('new_api_token');

        $client = ApiClient::query()->firstOrFail();
        $this->assertSame(1, $client->tenant_id);
        $this->assertNotNull($client->token_hash);
        $this->actingAs($admin)->delete(route('settings.api-integrations.destroy', $client))
            ->assertRedirect(route('settings.api-integrations'));
        $this->assertNotNull($client->fresh()->revoked_at);
    }
}
