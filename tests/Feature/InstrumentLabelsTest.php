<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Instruments\Form;
use App\Livewire\Instruments\Units;
use App\Models\ChoirCommission;
use App\Models\Instrument;
use App\Models\InstrumentMovement;
use App\Models\InstrumentUnit;
use App\Models\Member;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InstrumentIdentity;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InstrumentLabelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_reference_assigns_one_unique_stable_identifier_per_piece(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($admin)->test(Form::class)->set('name', 'Micro de chant')->set('quantity', '3')->call('save')->assertHasNoErrors();
        $instrument = Instrument::firstOrFail();
        $codes = $instrument->units()->orderBy('ordinal')->pluck('inventory_code')->all();
        $this->assertCount(3, array_unique($codes));
        $this->assertStringEndsWith('-0001', $codes[0]);
        $this->assertSame(0, app(InstrumentIdentity::class)->generate($instrument->id));
        $this->assertSame($codes, $instrument->units()->orderBy('ordinal')->pluck('inventory_code')->all());
        Livewire::actingAs($admin)->test(Form::class, ['instrument' => $instrument])->set('quantity', '4')->call('save')->assertHasNoErrors();
        $this->assertSame(4, $instrument->units()->count());
    }

    public function test_print_uses_existing_codes_without_mutation_and_offsets_onto_second_sheet(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $instrument = Instrument::factory()->create(['quantity' => 2]);
        app(InstrumentIdentity::class)->generate($instrument->id);
        $ids = $instrument->units()->pluck('id')->all();
        $codes = $instrument->units()->pluck('inventory_code')->all();
        $response = $this->post(route('instruments.labels'), ['units' => $ids, 'start' => 24]);
        $response->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('@page{size:A4', false)->assertSee('<svg', false)->assertDontSee('<!DOCTYPE svg', false);
        $this->assertSame(2, substr_count($response->getContent(), '<main class="sheet"'));
        foreach ($codes as $code) {
            $response->assertSee($code);
        }
        $this->assertSame(2, InstrumentUnit::count());
    }

    public function test_retirement_and_reactivation_preserve_codes_and_stock_and_never_reuse_codes(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin);
        $instrument = Instrument::factory()->create(['quantity' => 2]);
        $service = app(InstrumentIdentity::class);
        $service->generate($instrument->id);
        $unit = $instrument->units()->orderBy('ordinal')->firstOrFail();
        $code = $unit->inventory_code;
        $service->setRetired($unit->id, true);
        $this->assertSame(1, $instrument->fresh()->quantity);
        $this->assertNotNull($unit->fresh()->retired_at);
        Livewire::actingAs($admin)->test(Form::class, ['instrument' => $instrument->fresh()])->set('quantity', '2')->call('save')->assertHasNoErrors();
        $this->assertSame(3, $instrument->units()->count());
        $this->assertStringEndsWith('-0003', $instrument->units()->latest('ordinal')->value('inventory_code'));
        $service->setRetired($unit->id, false);
        $this->assertSame($code, $unit->fresh()->inventory_code);
        $this->assertSame(3, $instrument->fresh()->quantity);
    }

    public function test_stock_cannot_shrink_by_silently_reassigning_printed_codes(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $instrument = Instrument::factory()->create(['quantity' => 2]);
        $this->actingAs($admin);
        app(InstrumentIdentity::class)->generate($instrument->id);
        Livewire::test(Form::class, ['instrument' => $instrument])->set('quantity', '1')->call('save')->assertHasErrors('quantity');
        $this->assertSame(2, $instrument->fresh()->quantity);
        $this->assertSame(2, $instrument->units()->whereNull('retired_at')->count());
    }

    public function test_open_loans_prevent_retirement_of_an_ambiguous_physical_piece(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $instrument = Instrument::factory()->create(['quantity' => 2]);
        $this->actingAs($admin);
        app(InstrumentIdentity::class)->generate($instrument->id);
        InstrumentMovement::factory()->create(['instrument_id' => $instrument->id, 'type' => 'pret', 'quantity' => 1, 'actual_return_on' => null]);
        Livewire::test(Units::class)->call('retire', $instrument->units()->first()->id, true)->assertHasErrors('operation');
        $this->assertSame(2, $instrument->fresh()->quantity);
    }

    public function test_reader_can_print_but_cannot_generate_or_retire(): void
    {
        $member = Member::factory()->create();
        $reader = User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        $commission = ChoirCommission::create(['name' => 'Instruments', 'name_key' => 'instruments', 'mission' => 'Inventaire', 'kind' => 'permanent', 'starts_on' => today(), 'module_key' => 'instruments']);
        $commission->members()->attach($member->id);
        $instrument = Instrument::factory()->create(['quantity' => 1]);
        app(InstrumentIdentity::class)->synchronize($instrument->id);
        $unit = $instrument->units()->firstOrFail();
        $this->actingAs($reader)->get(route('instruments.units'))->assertOk();
        $this->post(route('instruments.labels'), ['units' => [$unit->id], 'start' => 1])->assertOk();
        Livewire::actingAs($reader)->test(Units::class)->set('instrumentId', $instrument->id)->call('generate')->assertForbidden();
        Livewire::actingAs($reader)->test(Units::class)->call('retire', $unit->id, true)->assertForbidden();
    }

    public function test_print_rejects_retired_duplicates_and_invalid_start_position(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $instrument = Instrument::factory()->create(['quantity' => 2]);
        app(InstrumentIdentity::class)->generate($instrument->id);
        $unit = $instrument->units()->firstOrFail();
        $this->post(route('instruments.labels'), ['units' => [$unit->id, $unit->id], 'start' => 1])->assertSessionHasErrors('units.0');
        $this->post(route('instruments.labels'), ['units' => [$unit->id], 'start' => 25])->assertSessionHasErrors('start');
        app(InstrumentIdentity::class)->setRetired($unit->id, true);
        $this->post(route('instruments.labels'), ['units' => [$unit->id], 'start' => 1])->assertSessionHasErrors('units');
    }

    public function test_codes_are_immutable_and_remain_after_reference_is_archived(): void
    {
        $instrument = Instrument::factory()->create(['quantity' => 1]);
        app(InstrumentIdentity::class)->synchronize($instrument->id);
        $unit = $instrument->units()->firstOrFail();
        $instrument->delete();
        $this->assertDatabaseHas('instrument_units', ['id' => $unit->id]);
        $this->expectException(HttpException::class);
        $unit->forceFill(['inventory_code' => 'REASSIGNED'])->save();
    }

    public function test_foreign_tenant_units_are_not_printable_and_identifiers_are_globally_unique(): void
    {
        config(['tenancy.enabled' => true]);
        $ctx = app(TenantContext::class);
        $a = Tenant::forceCreate(['name' => 'A', 'slug' => 'a', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $b = Tenant::forceCreate(['name' => 'B', 'slug' => 'b', 'trial_started_at' => now(), 'trial_ends_at' => now()->addDays(3)]);
        $foreign = $ctx->run($b->id, function () {
            $i = Instrument::factory()->create(['quantity' => 1]);
            app(InstrumentIdentity::class)->synchronize($i->id);

            return $i->units()->first();
        });
        $ctx->run($a->id, function () use ($foreign) {
            $admin = User::factory()->role(UserRole::Admin)->create();
            $i = Instrument::factory()->create(['quantity' => 1]);
            app(InstrumentIdentity::class)->synchronize($i->id);
            $this->assertNotSame($foreign->inventory_code, $i->units()->value('inventory_code'));
            $this->actingAs($admin)->post(route('instruments.labels'), ['units' => [$foreign->id], 'start' => 1])->assertSessionHasErrors('units');
        });
    }
}
