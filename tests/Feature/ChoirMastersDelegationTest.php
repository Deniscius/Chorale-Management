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
            $commission = ChoirCommission::create([
                'name' => 'Commission liturgique',
                'name_key' => 'commission liturgique',
                'kind' => 'permanent',
                'mission' => 'Direction musicale des répétitions et des célébrations',
                'starts_on' => today()->subWeek()->toDateString(),
                'module_key' => UserRole::MODULE_LITURGIQUE,
            ]);

            app(CommissionResponsibilities::class)->assign(
                $commission->id, $chief->id, null, UserRole::MODULE_LITURGIQUE, [], [$assistant->id]
            );
            $this->assertSame($chief->id, $commission->fresh()->leader_id);
            $this->assertSame([$assistant->id], $commission->choirMasters()->pluck('users.id')->all());
            $this->assertContains(UserRole::MODULE_LITURGIQUE, app(CommissionAccess::class)->modules($assistant, true));

            app(CommissionResponsibilities::class)->assign(
                $commission->id, $chief->id, null, UserRole::MODULE_LITURGIQUE, [], []
            );
            $this->assertCount(0, $commission->choirMasters);
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
            $commission = ChoirCommission::create([
                'name' => 'Commission liturgique',
                'name_key' => 'commission liturgique',
                'kind' => 'permanent',
                'mission' => 'Animer le chant et la liturgie',
                'starts_on' => today()->subWeek()->toDateString(),
                'module_key' => UserRole::MODULE_LITURGIQUE,
            ]);

            $this->expectException(ValidationException::class);
            app(CommissionResponsibilities::class)->assign(
                $commission->id, null, null, UserRole::MODULE_LITURGIQUE, [], [$assistant->id]
            );
        });
    }
}
