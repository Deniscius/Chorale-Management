<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\User;
use App\Services\MemberOnboarding;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MemberOnboardingTenantTest extends TestCase
{
    use RefreshDatabase;

    private function space(string $name): User
    {
        return app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable', 'username' => $name, 'email' => $name.'@example.test', 'password' => 'SecretResponsable2026']);
    }

    public function test_activation_uses_only_the_token_tenant_and_ignores_forged_membership(): void
    {
        config(['tenancy.enabled' => true]);
        $a = $this->space('alpha');
        $b = $this->space('beta');
        $context = app(TenantContext::class);
        $member = $context->run($a->tenant_id, fn () => Member::factory()->create(['first_name' => 'Émile Pierre']));
        $other = $context->run($b->tenant_id, fn () => Member::factory()->create());
        $url = $context->run($a->tenant_id, fn () => app(MemberOnboarding::class)->issue($member));
        $this->get($url)->assertOk()->assertViewHas('username', 'emile');
        $this->post($url, ['username' => 'emile', 'email' => 'emile@example.test', 'password' => 'MotPersonnel2026', 'password_confirmation' => 'MotPersonnel2026', 'tenant_id' => $b->tenant_id, 'member_id' => $other->id, 'role' => 'admin'])->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['tenant_id' => $a->tenant_id, 'member_id' => $member->id, 'role' => 'choriste']);
        $this->assertDatabaseMissing('users', ['member_id' => $other->id]);
        $this->assertNull($context->id());
    }

    public function test_sql_rejects_an_invitation_for_a_member_of_another_tenant(): void
    {
        config(['tenancy.enabled' => true]);
        $a = $this->space('alpha');
        $b = $this->space('beta');
        $member = app(TenantContext::class)->run($b->tenant_id, fn () => Member::factory()->create());
        $this->expectException(QueryException::class);
        DB::table('member_invitations')->insert(['tenant_id' => $a->tenant_id, 'member_id' => $member->id, 'role' => 'choriste', 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay()]);
    }
}
