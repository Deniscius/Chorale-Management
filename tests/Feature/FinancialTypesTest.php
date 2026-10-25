<?php

namespace Tests\Feature;

use App\Enums\DonationType;
use App\Enums\UserRole;
use App\Livewire\Contributions\Form as ContributionForm;
use App\Livewire\Donations\Form as DonationForm;
use App\Livewire\Settings\FinancialTypes;
use App\Models\Contribution;
use App\Models\Donation;
use App\Models\FinancialType;
use App\Models\Member;
use App\Models\User;
use App\Services\FinancialCatalog;
use App\Services\ReceiptLedger;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class FinancialTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true]);
    }

    private function space(string $name = 'alpha'): User
    {
        return app(TenantRegistration::class)->create(['organization' => 'Chorale '.$name, 'name' => 'Responsable', 'username' => $name, 'email' => $name.'@example.test', 'password' => 'SecretChorale2026']);
    }

    private function within(User $user, \Closure $callback): mixed
    {
        return app(TenantContext::class)->run($user->tenant_id, $callback);
    }

    private function createType(User $admin, string $kind = 'contribution', string $name = 'Projet chorale', ?string $amount = '1500', string $base = 'autre'): FinancialType
    {
        return $this->within($admin, function () use ($admin, $kind, $name, $amount, $base) {
            Livewire::actingAs($admin)->test(FinancialTypes::class)->set('kind', $kind)->set('label', $name)->set('default_amount', $amount)->set('base_type', $base)->call('save')->assertHasNoErrors();

            return FinancialType::where('label', $name)->firstOrFail();
        });
    }

    public function test_each_new_choir_gets_its_own_defaults_without_resetting_another(): void
    {
        $a = $this->space();
        $this->within($a, fn () => FinancialType::where('code', 'mensuelle')->firstOrFail()->update(['label' => 'Participation mensuelle', 'default_amount' => 3500]));
        $b = $this->space('beta');
        $this->within($a, fn () => $this->assertSame('Participation mensuelle', app(FinancialCatalog::class)->label('mensuelle')));
        $this->within($b, function () {
            $this->assertSame(9, FinancialType::count());
            $this->assertSame('Cotisation mensuelle', app(FinancialCatalog::class)->label('mensuelle'));
            $this->assertSame('2000.00', FinancialType::where('code', 'mensuelle')->value('default_amount'));
        });
    }

    public function test_admin_can_create_modify_and_disable_a_type_without_changing_its_code(): void
    {
        $admin = $this->space();
        $type = $this->createType($admin);
        $this->within($admin, function () use ($admin, $type) {
            Livewire::actingAs($admin)->test(FinancialTypes::class)->call('edit', $type->id)->set('label', 'Uniformes')->set('default_amount', '7500')->set('is_active', false)->call('save')->assertHasNoErrors();
            $this->assertSame('Uniformes', $type->fresh()->label);
            $this->assertSame($type->code, $type->fresh()->code);
            $this->assertFalse($type->fresh()->is_active);
        });
    }

    public function test_names_are_unique_within_a_section_after_normalizing_case_and_spaces(): void
    {
        $admin = $this->space();
        $this->createType($admin, name: 'Projet chorale');
        $this->within($admin, fn () => Livewire::actingAs($admin)->test(FinancialTypes::class)->set('label', '  PROJET   chorale ')->call('save')->assertHasErrors('label'));
        $this->createType($admin, 'donation', 'Projet chorale');
    }

    public function test_only_administrators_can_configure_the_catalog(): void
    {
        $admin = $this->space();
        $treasurer = $this->within($admin, fn () => User::factory()->role(UserRole::Tresorier)->create());
        $this->actingAs($treasurer)->get('/parametres/contributions')->assertForbidden();
        $this->within($admin, fn () => Livewire::actingAs($treasurer)->test(FinancialTypes::class)->assertForbidden());
    }

    public function test_modified_default_amount_does_not_change_existing_dues_or_payments(): void
    {
        $admin = $this->space();
        $this->within($admin, function () use ($admin) {
            $member = Member::factory()->create();
            $old = app(ReceiptLedger::class)->saveContribution(null, ['member_id' => $member->id, 'year' => 2026, 'month' => 1, 'due_amount' => 2000, 'paid_amount' => 0]);
            $type = FinancialType::where('code', 'mensuelle')->firstOrFail();
            Livewire::actingAs($admin)->test(FinancialTypes::class)->call('edit', $type->id)->set('default_amount', '3500')->call('save')->assertHasNoErrors();
            Livewire::test(ContributionForm::class)->assertSet('due_amount', '3500.00');
            $this->assertSame('2000.00', $old->fresh()->due_amount);
            $this->assertSame('0.00', $old->fresh()->paid_amount);
        });
    }

    public function test_a_member_can_have_two_different_dues_for_the_same_month(): void
    {
        $admin = $this->space();
        $type = $this->createType($admin);
        $this->within($admin, function () use ($admin, $type) {
            $member = Member::factory()->create();
            foreach (['mensuelle', $type->code] as $code) {
                Livewire::actingAs($admin)->test(ContributionForm::class)->set('member_id', (string) $member->id)->set('year', '2026')->set('month', '1')->set('type_code', $code)->set('due_amount', '2000')->call('save')->assertHasNoErrors();
            }
            $this->assertSame(2, Contribution::where('member_id', $member->id)->count());
            Livewire::test(ContributionForm::class)->set('member_id', (string) $member->id)->set('year', '2026')->set('month', '1')->set('type_code', 'mensuelle')->call('save')->assertHasErrors('month');
        });
    }

    public function test_sql_still_rejects_a_duplicate_member_type_and_period(): void
    {
        $admin = $this->space();
        $this->within($admin, function () {
            $member = Member::factory()->create();
            Contribution::factory()->create(['member_id' => $member->id, 'year' => 2026, 'month' => 1]);
            $this->expectException(QueryException::class);
            Contribution::factory()->create(['member_id' => $member->id, 'year' => 2026, 'month' => 1]);
        });
    }

    public function test_custom_financial_donation_prepares_a_draft_receipt(): void
    {
        $admin = $this->space();
        $type = $this->createType($admin, 'donation', 'Collecte transport', '1200');
        $this->within($admin, function () use ($admin, $type) {
            Livewire::actingAs($admin)->test(DonationForm::class)->set('donation_type', $type->code)->set('donor_type', 'anonyme')->set('amount', '1200')->call('save')->assertHasNoErrors();
            $donation = Donation::firstOrFail();
            $this->assertSame($type->code, $donation->type_code);
            $this->assertSame(DonationType::Autre, $donation->donation_type);
            $this->assertNotNull($donation->transaction_id);
            $this->assertSame('1200.00', $donation->transaction->amount);
        });
    }

    public function test_custom_in_kind_donation_never_creates_a_cash_receipt(): void
    {
        $admin = $this->space();
        $type = $this->createType($admin, 'donation', 'Offre de matériel', null, 'don_materiel');
        $this->within($admin, function () use ($admin, $type) {
            Livewire::actingAs($admin)->test(DonationForm::class)->set('donation_type', $type->code)->set('donor_type', 'anonyme')->set('amount', '10000')->call('save')->assertHasNoErrors();
            $this->assertNull(Donation::firstOrFail()->transaction_id);
            $this->assertDatabaseCount('transactions', 0);
        });
    }

    public function test_inactive_type_blocks_new_dues_but_keeps_old_dues_payable(): void
    {
        $admin = $this->space();
        $this->within($admin, function () use ($admin) {
            $member = Member::factory()->create();
            $old = app(ReceiptLedger::class)->saveContribution(null, ['member_id' => $member->id, 'year' => 2026, 'month' => 1, 'due_amount' => 2000, 'paid_amount' => 0]);
            FinancialType::where('code', 'mensuelle')->firstOrFail()->update(['is_active' => false]);
            Livewire::actingAs($admin)->test(ContributionForm::class)->set('member_id', (string) $member->id)->set('year', '2026')->set('month', '2')->call('save')->assertHasErrors('type_code');
            $old = app(ReceiptLedger::class)->saveContribution($old, ['paid_amount' => 500]);
            $this->assertSame('500.00', $old->paid_amount);
            $this->assertSame('Cotisation mensuelle', $old->typeLabel());
        });
    }

    public function test_validated_contribution_cannot_be_moved_to_another_type(): void
    {
        $admin = $this->space();
        $type = $this->createType($admin);
        $this->within($admin, function () use ($type) {
            $old = app(ReceiptLedger::class)->saveContribution(null, ['member_id' => Member::factory()->create()->id, 'year' => 2026, 'month' => 1, 'due_amount' => 2000, 'paid_amount' => 500]);
            DB::table('transactions')->where('contribution_id', $old->id)->update(['status' => 'validee']);
            $this->expectException(ValidationException::class);
            app(ReceiptLedger::class)->saveContribution($old, ['type_code' => $type->code]);
        });
    }

    public function test_a_catalog_type_cannot_switch_its_accounting_nature(): void
    {
        $admin = $this->space();
        $this->within($admin, function () {
            $this->expectException(ValidationException::class);
            FinancialType::where('code', 'offrande_messe')->firstOrFail()->forceFill(['base_type' => 'don_materiel'])->save();
        });
    }

    public function test_cross_tenant_type_is_not_available_and_cannot_be_referenced_in_sql(): void
    {
        $a = $this->space();
        $b = $this->space('beta');
        $type = $this->createType($a);
        $this->within($b, function () use ($b, $type) {
            Livewire::actingAs($b)->test(ContributionForm::class)->set('member_id', (string) Member::factory()->create()->id)->set('type_code', $type->code)->set('due_amount', '2000')->call('save')->assertHasErrors('type_code');
            $member = Member::factory()->create();
            $this->expectException(QueryException::class);
            DB::table('contributions')->insert(['tenant_id' => $b->tenant_id, 'member_id' => $member->id, 'type_code' => $type->code, 'year' => 2026, 'month' => 1, 'due_amount' => 1000]);
        });
    }

    public function test_an_admin_cannot_edit_a_type_of_another_choir(): void
    {
        $a = $this->space();
        $b = $this->space('beta');
        $type = $this->createType($a);
        $this->within($b, fn () => Livewire::actingAs($b)->test(FinancialTypes::class)->call('edit', $type->id)->assertNotFound());
    }

    public function test_renamed_sections_are_visible_only_in_their_own_choir(): void
    {
        $a = $this->space();
        $b = $this->space('beta');
        $this->within($a, fn () => Livewire::actingAs($a)->test(FinancialTypes::class)->set('donation_label', 'Soutiens solidaires')->set('contribution_label', 'Participations')->call('saveLabels')->assertRedirect(route('settings.financial-types')));
        $this->actingAs($a)->get('/dons')->assertOk()->assertSee('Soutiens solidaires');
        $this->get('/cotisations')->assertOk()->assertSee('Participations');
        $this->actingAs($b)->get('/dons')->assertOk()->assertDontSee('Soutiens solidaires');
    }

    public function test_settings_render_and_changing_a_type_preserves_manually_entered_amounts(): void
    {
        $admin = $this->space();
        $type = $this->createType($admin);
        $gift = $this->createType($admin, 'donation', 'Soutien libre', '2500');
        $this->actingAs($admin)->get('/parametres/contributions')->assertOk()->assertSee('Types de contribution');
        $this->within($admin, function () use ($admin, $type, $gift) {
            Livewire::actingAs($admin)->test(ContributionForm::class)->set('due_amount', '6500')->set('type_code', $type->code)->assertSet('due_amount', '6500');
            Livewire::test(DonationForm::class)->set('amount', '4200')->set('donation_type', $gift->code)->assertSet('amount', '4200');
        });
        $this->get('/abonnement')->assertOk()->assertSee('2 000 FCFA');
    }
}
