<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Form as ActivityForm;
use App\Livewire\Instruments\Form as InstrumentForm;
use App\Livewire\Members\Form as MemberForm;
use App\Models\ChoirCommission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InputQualityValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_names_are_normalized_and_reject_markup_or_numbers_only(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        Livewire::actingAs($admin)->test(MemberForm::class)
            ->set('first_name', '<script>')
            ->set('last_name', '12345')
            ->call('save')
            ->assertHasErrors(['first_name', 'last_name']);

        Livewire::actingAs($admin)->test(MemberForm::class)
            ->set('first_name', '  Marie   Claire ')
            ->set('last_name', ' D’Almeida ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('members', ['first_name' => 'Marie Claire', 'last_name' => 'D’Almeida']);
    }

    public function test_business_labels_reject_control_characters_and_require_words(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        ChoirCommission::create([
            'name' => 'Commission activités', 'name_key' => 'commission-activites', 'kind' => 'permanente',
            'mission' => 'Organiser les activités', 'starts_on' => today(), 'module_key' => 'activites',
        ]);

        Livewire::actingAs($admin)->test(InstrumentForm::class)
            ->set('name', '<b>Piano</b>')
            ->set('quantity', '1')
            ->call('save')
            ->assertHasErrors('name');

        Livewire::actingAs($admin)->test(ActivityForm::class)
            ->set('title', '123456')
            ->call('save')
            ->assertHasErrors('title');
    }
}
