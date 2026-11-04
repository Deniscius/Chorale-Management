<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Models\User;
use App\Services\CommissionAccess;
use App\Services\CommissionResponsibilities;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ChoirMastersDelegationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_assistant_receives_liturgical_management_and_can_be_revoked(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale de formation',
            'name' => 'Administration',
            'username' => 'master-admin',
            'email' => 'master-admin@example.test',
            'password' => 'SecurePassword2026!',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            $this->actingAs($admin);
            $chief = User::factory()->role(UserRole::Choriste)->create();
            $assistant = User::factory()->role(UserRole::Choriste)->create();
            $commission = ChoirCommission::query()
                ->where('archive_commission', \App\Enums\Commission::Liturgique->value)
                ->firstOrFail();

            app(CommissionResponsibilities::class)->assign(
                $commission->id, $chief->id, null, UserRole::MODULE_LITURGIQUE, [], [$assistant->id]
            );
            $this->assertSame($chief->id, $commission->fresh()->leader_id);
            $this->assertSame([$assistant->id], $commission->choirMasters()->pluck('users.id')->all());
            $this->assertContains(UserRole::MODULE_LITURGIQUE, app(CommissionAccess::class)->modules($assistant, true));

            app(CommissionResponsibilities::class)->assign(
                $commission->id, $chief->id, null, UserRole::MODULE_LITURGIQUE, [], []
            );
            $this->assertSame(0, $commission->choirMasters()->count());
            $this->assertNotContains(UserRole::MODULE_LITURGIQUE, app(CommissionAccess::class)->modules($assistant, true));
        });
    }

    public function test_master_assistant_requires_a_designated_head_master(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = app(TenantRegistration::class)->create([
            'organization' => 'Chorale Sainte Cécile',
            'name' => 'Administration',
            'username' => 'cecilia-admin',
            'email' => 'cecilia-admin@example.test',
            'password' => 'SecurePassword2026!',
        ]);

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            $this->actingAs($admin);
            $assistant = User::factory()->role(UserRole::Choriste)->create();
            $commission = ChoirCommission::query()
                ->where('archive_commission', \App\Enums\Commission::Liturgique->value)
                ->firstOrFail();

            $this->expectException(ValidationException::class);
            app(CommissionResponsibilities::class)->assign(
                $commission->id, null, null, UserRole::MODULE_LITURGIQUE, [], [$assistant->id]
            );
        });
    }
}
