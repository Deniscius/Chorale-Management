<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\UserRole;
use App\Models\ChoirCommission;
use App\Models\User;
use App\Services\ChoirMasterAssociates;
use App\Services\CommissionAccess;
use App\Services\CommissionResponsibilities;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class TenantCommissionsAndChoirMasterAssociatesTest extends TestCase
{
    use RefreshDatabase;

    private function register(string $slug): User
    {
        return app(TenantRegistration::class)->create([
            'organization' => 'Chorale '.$slug,
            'name' => 'Direction',
            'username' => 'direction-'.$slug,
            'email' => 'direction-'.$slug.'@example.test',
            'password' => 'TestingPassword2026!',
        ]);
    }

    public function test_new_tenants_are_not_forced_to_adopt_predefined_commissions(): void
    {
        config(['tenancy.enabled' => true]);
        $a = $this->register('alpha');
        $b = $this->register('beta');

        app(TenantContext::class)->run($a->tenant_id, function () use ($a): void {
            $this->actingAs($a);
            $this->assertSame(0, ChoirCommission::query()->count());
            ChoirCommission::create([
                'name' => 'Chant et liturgie', 'name_key' => 'chant et liturgie',
                'kind' => 'permanent', 'mission' => 'Organiser les chants de la chorale.',
                'starts_on' => today(), 'module_key' => UserRole::MODULE_LITURGIQUE,
            ]);
            $this->assertSame(1, ChoirCommission::query()->count());
        });
        app(TenantContext::class)->run($b->tenant_id, function (): void {
            $this->assertSame(0, ChoirCommission::query()->count());
        });
    }

    public function test_head_choir_master_can_choose_associates_but_ordinary_member_cannot(): void
    {
        config(['tenancy.enabled' => true]);
        $admin = $this->register('choir-master');

        app(TenantContext::class)->run($admin->tenant_id, function () use ($admin): void {
            $this->actingAs($admin);
            $head = User::factory()->role(UserRole::Choriste)->create();
            $assistant = User::factory()->role(UserRole::Choriste)->create();
            $other = User::factory()->role(UserRole::Choriste)->create();
            $commission = ChoirCommission::create([
                'name' => 'Direction du chant', 'name_key' => 'direction du chant',
                'kind' => 'permanent', 'mission' => 'Coordonner les maîtres de chœur.',
                'starts_on' => today()->subDay(),
                'module_key' => UserRole::MODULE_LITURGIQUE,
            ]);
            app(CommissionResponsibilities::class)->assign($commission->id, $head->id, null, UserRole::MODULE_LITURGIQUE, [], []);

            $this->actingAs($head);
            app(ChoirMasterAssociates::class)->sync($commission->id, [$assistant->id]);
            $this->assertSame([$assistant->id], $commission->choirMasters()->pluck('users.id')->all());

            $this->actingAs($other);
            try {
                app(ChoirMasterAssociates::class)->sync($commission->id, []);
                $this->fail('Un choriste ordinaire ne peut pas retirer les associés du maître de chœur.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertSame(1, $commission->choirMasters()->count());
        });
    }

    public function test_follow_up_role_has_no_liturgical_management_privilege(): void
    {
        $this->assertFalse(UserRole::CommissionSuivieFormation->canManageModule(UserRole::MODULE_LITURGIQUE));
        $this->assertFalse(UserRole::CommissionSuivieFormation->canAccessModule(UserRole::MODULE_LITURGIQUE));
        $this->assertTrue(UserRole::CommissionSuivieFormation->canAccessModule(UserRole::MODULE_MEMBERS));
        $this->assertSame(UserRole::MODULE_MEMBERS, Commission::SuivieFormation->module());
    }
}
