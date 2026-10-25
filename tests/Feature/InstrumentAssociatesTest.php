<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Commissions\Responsibilities;
use App\Livewire\Instruments\Form;
use App\Livewire\Instruments\Index;
use App\Models\ChoirCommission;
use App\Models\Instrument;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CommissionAccess;
use App\Services\CommissionResponsibilities;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class InstrumentAssociatesTest extends TestCase
{
    use RefreshDatabase;

    private function commission(): ChoirCommission
    {
        return ChoirCommission::create(['name' => 'Instruments', 'name_key' => 'instruments', 'kind' => 'permanent', 'mission' => 'Gérer le matériel', 'starts_on' => today(), 'module_key' => 'instruments']);
    }

    public function test_leader_and_multiple_associates_can_create_update_and_remove_stock(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $associates = User::factory()->count(2)->role(UserRole::Choriste)->create();
        $entry = $this->commission();
        Livewire::actingAs($admin)->test(Responsibilities::class, ['commissionId' => $entry->id])
            ->set('leader_id', (string) $leader->id)->set('instrument_associate_ids', $associates->modelKeys())->call('save')->assertHasNoErrors();
        foreach ([$leader, ...$associates] as $user) {
            Livewire::actingAs($user)->test(Form::class)->set('name', 'Micro '.$user->id)->set('quantity', '2')->call('save')->assertHasNoErrors();
            $instrument = Instrument::where('name', 'Micro '.$user->id)->firstOrFail();
            $codes = $instrument->units()->pluck('inventory_code')->all();
            Livewire::actingAs($user)->test(Form::class, ['instrument' => $instrument])->set('name', 'Micro modifié '.$user->id)->call('save')->assertHasNoErrors();
            $this->actingAs($user)->post(route('instruments.labels'), ['units' => $instrument->units()->pluck('id')->all(), 'start' => 1])->assertOk()->assertSee('border:0.2mm dashed #64748b', false);
            Livewire::actingAs($user)->test(Index::class)->call('delete', $instrument->id)->assertHasNoErrors();
            $this->assertSoftDeleted($instrument);
            $this->assertSame($codes, $instrument->units()->pluck('inventory_code')->all());
            $this->assertSame(UserRole::Choriste, $user->fresh()->role);
        }
    }

    public function test_reader_cannot_manage_and_removed_associate_loses_access_on_open_page(): void
    {
        $member = Member::factory()->create();
        $reader = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $entry = $this->commission();
        $entry->members()->attach($member->id);
        $this->actingAs($admin);
        app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, null, 'instruments', [$associate->id]);
        Livewire::actingAs($reader)->test(Index::class)->call('create')->assertForbidden();
        $page = Livewire::actingAs($associate)->test(Index::class);
        $this->actingAs($admin);
        app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, null, 'instruments', []);
        Livewire::actingAs($associate);
        $page->call('create')->assertForbidden();
    }

    public function test_expiry_and_module_change_revoke_associate_management(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $entry = $this->commission();
        $this->actingAs($admin);
        app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, null, 'instruments', [$associate->id]);
        $this->assertTrue($associate->canManageModule('instruments'));
        $this->assertFalse($associate->canManageModule('liturgique'));
        $entry->update(['ends_on' => today()->subDay()]);
        app(CommissionAccess::class)->forget();
        $this->assertFalse($associate->canManageModule('instruments'));
        app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, null, 'liturgique', []);
        $this->assertCount(0, $entry->instrumentAssociates()->get());
        $this->assertFalse($associate->canManageModule('liturgique'));
    }

    public function test_associate_cannot_delegate_and_foreign_accounts_are_rejected(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $leader = User::factory()->role(UserRole::Choriste)->create();
        $associate = User::factory()->role(UserRole::Choriste)->create();
        $entry = $this->commission();
        Livewire::actingAs($associate)->test(Responsibilities::class, ['commissionId' => $entry->id])->assertForbidden();
        config(['tenancy.enabled' => true]);
        $other = Tenant::forceCreate(['name' => 'Autre chorale', 'slug' => 'autre-chorale', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $foreign = app(TenantContext::class)->run($other->id, fn () => User::factory()->role(UserRole::Choriste)->create());
        app(TenantContext::class)->set($admin->tenant_id);
        $this->actingAs($admin);
        try {
            app(CommissionResponsibilities::class)->assign($entry->id, $leader->id, null, 'instruments', [$foreign->id]);
            $this->fail('Foreign assignment accepted');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('instrument_associate_ids.0', $e->errors());
        }
        $this->assertCount(0, $entry->instrumentAssociates()->get());
    }
}
