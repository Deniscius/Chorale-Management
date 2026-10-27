<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Instruments\Form;
use App\Livewire\Instruments\MovementForm;
use App\Livewire\Instruments\Movements;
use App\Models\Instrument;
use App\Models\InstrumentMovement;
use App\Models\User;
use App\Services\InstrumentIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuidedMaterialTest extends TestCase
{
    use RefreshDatabase;

    public function test_friendly_labels_preserve_the_object_codes_and_print_layout(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Admin)->create());
        $instrument = Instrument::factory()->create(['name' => 'Microphone', 'quantity' => 2]);
        app(InstrumentIdentity::class)->generate($instrument->id);
        $units = $instrument->units()->orderBy('ordinal')->get();
        $codes = $units->pluck('inventory_code')->all();
        $this->get(route('instruments.units'))
            ->assertOk()->assertSee('Microphone n° 1')->assertSee('Microphone n° 2')
            ->assertSee('Le numéro de l’objet n’est pas sa quantité.')
            ->assertSee('Voir 0 étiquette(s) avant impression')->assertDontSee('Exemplaire');
        $this->post(route('instruments.labels'), ['units' => $units->modelKeys(), 'start' => 1])
            ->assertOk()->assertSee('n° 1')->assertSee('n° 2')->assertDontSee('Exemplaire')
            ->assertSee('grid-template-rows:repeat(8,33.9mm)', false);
        $this->assertSame($codes, $instrument->units()->orderBy('ordinal')->pluck('inventory_code')->all());
        $this->assertSame(2, $instrument->fresh()->quantity);
    }

    public function test_material_form_keeps_optional_fields_and_reveals_purchase_errors(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $form = Livewire::actingAs($admin)->test(Form::class)
            ->assertSee('Identifier le matériel')->assertSee('Savoir où le retrouver')
            ->set('name', 'Microphone guidé')->set('quantity', '2')->set('purchase_price', '-10')
            ->call('save')->assertHasErrors(['purchase_price']);
        $this->assertMatchesRegularExpression('/<details[^>]*\sopen\b/', $form->html());
        $form->set('purchase_price', '1000')->set('location', 'Armoire de la chorale')
            ->call('save')->assertHasNoErrors();
        $instrument = Instrument::where('name', 'Microphone guidé')->sole();
        $this->assertSame(2, $instrument->units()->count());
        $this->assertSame('Armoire de la chorale', $instrument->location);
        $this->assertSame('1000.00', $instrument->purchase_price);
        $codes = $instrument->units()->pluck('inventory_code')->all();
        Livewire::actingAs($admin)->test(Form::class, ['instrument' => $instrument])
            ->set('name', 'Microphone renommé')->call('save')->assertHasNoErrors();
        $this->assertSame('1000.00', $instrument->fresh()->purchase_price);
        $this->assertSame($codes, $instrument->units()->pluck('inventory_code')->all());

    }

    public function test_guided_loan_and_return_reuse_the_existing_stock_rules(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $instrument = Instrument::factory()->create(['quantity' => 2]);
        $this->actingAs($admin)->get(route('instruments.movements'))->assertOk()
            ->assertSee('Suivre les prêts et retours');
        Livewire::actingAs($admin)->test(MovementForm::class)
            ->assertSee('Choisir le matériel et l’action')->assertSee('Date du retour réel')
            ->set('instrument_id', (string) $instrument->id)->set('type', 'pret')
            ->set('quantity', '2')->set('destination', 'Salle de répétition')
            ->call('save')->assertHasNoErrors();
        $loan = InstrumentMovement::sole();
        Livewire::actingAs($admin)->test(MovementForm::class)
            ->set('instrument_id', (string) $instrument->id)->set('type', 'pret')
            ->set('quantity', '1')->call('save')->assertHasErrors(['quantity']);
        Livewire::actingAs($admin)->test(Movements::class)
            ->assertSee('Matériel rendu')->call('close', $loan->id)->assertHasNoErrors();
        $this->assertNotNull($loan->fresh()->actual_return_on);
        $this->assertSame(1, InstrumentMovement::count());
        $this->assertSame(2, $instrument->fresh()->quantity);
    }
}
