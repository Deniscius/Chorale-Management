<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Activities\Participants;
use App\Livewire\Instruments\Movements;
use App\Livewire\Users\Form;
use App\Models\Activity;
use App\Models\Instrument;
use App\Models\InstrumentMovement;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Reminder;
use App\Models\User;
use App\Services\ReminderDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BugRegressionTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidEnumFields(): array
    {
        return [
            ['Activities\\Form', 'type'], ['Activities\\Form', 'status'],
            ['Budgets\\Form', 'category'], ['Contributions\\Form', 'status'],
            ['Correspondences\\Form', 'type'], ['Correspondences\\Form', 'channel'],
            ['Correspondences\\Form', 'confidentiality'], ['Correspondences\\Form', 'status'],
            ['Donations\\Form', 'donor_type'], ['Donations\\Form', 'donation_type'],
            ['Instruments\\Form', 'category'], ['Instruments\\Form', 'condition'],
            ['Instruments\\MaintenanceForm', 'type'], ['Instruments\\MaintenanceForm', 'status'],
            ['Instruments\\MovementForm', 'type'], ['Instruments\\MovementForm', 'condition_on_return'],
            ['Members\\Form', 'voice'], ['Members\\Form', 'status'],
            ['Performances\\Form', 'type'], ['Performances\\Form', 'status'],
            ['Rehearsals\\Form', 'type'], ['Rehearsals\\Form', 'status'],
            ['Transactions\\Form', 'type'], ['Transactions\\Form', 'status'],
            ['Transactions\\Form', 'payment_method'], ['Users\\Form', 'role'], ['Works\\Form', 'type'],
        ];
    }

    #[DataProvider('invalidEnumFields')]
    public function test_invalid_enum_values_are_rejected(string $form, string $field): void
    {
        $modelParameter = [
            'Activities' => 'activity', 'Budgets' => 'budget', 'Contributions' => 'contribution',
            'Correspondences' => 'correspondence', 'Donations' => 'donation', 'Instruments' => 'instrument',
            'Members' => 'member', 'Performances' => 'performance', 'Rehearsals' => 'rehearsal',
            'Transactions' => 'transaction', 'Users' => 'user', 'Works' => 'work',
        ][explode('\\', $form)[0]];
        if (str_contains($form, 'MaintenanceForm')) {
            $modelParameter = 'maintenance';
        }
        if (str_contains($form, 'MovementForm')) {
            $modelParameter = 'movement';
        }

        Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
            ->test('App\\Livewire\\'.$form, [$modelParameter => null])
            ->set($field, 'invalid-value')
            ->call('save')
            ->assertHasErrors([$field]);
    }

    public function test_failed_reminder_can_be_retried_once(): void
    {
        Mail::fake();
        $rehearsal = Rehearsal::factory()->create();
        $member = Member::factory()->active()->create(['notify_email' => true, 'notify_sms' => false]);
        $reminder = Reminder::create([
            'rehearsal_id' => $rehearsal->id, 'member_id' => $member->id,
            'channel' => 'mail', 'recipient' => 'old@example.com',
            'subject' => 'Old', 'body' => 'Old', 'status' => 'echec', 'error' => 'Transport unavailable',
        ]);
        $dispatcher = app(ReminderDispatcher::class);
        $this->assertSame(1, $dispatcher->dispatchForRehearsal($rehearsal)['sent']);
        $this->assertSame(1, $dispatcher->dispatchForRehearsal($rehearsal)['skipped']);
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('reminders', 1);
        $this->assertDatabaseHas('reminders', ['id' => $reminder->id, 'status' => 'envoye', 'error' => null, 'recipient' => $member->email]);
    }

    public function test_closing_and_deleting_movements_refreshes_availability(): void
    {
        $instrument = Instrument::factory()->create(['is_borrowed' => false]);
        $movement = InstrumentMovement::factory()->create(['instrument_id' => $instrument->id, 'type' => 'pret', 'actual_return_on' => null]);
        $this->assertTrue($instrument->refresh()->is_borrowed);
        Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
            ->test(Movements::class)->call('close', $movement->id);
        $this->assertFalse($instrument->refresh()->is_borrowed);
        $movement->refresh()->update(['actual_return_on' => null]);
        $this->assertTrue($instrument->refresh()->is_borrowed);
        $movement->delete();
        $this->assertFalse($instrument->refresh()->is_borrowed);
    }

    public function test_reassigning_a_movement_refreshes_both_instruments(): void
    {
        $first = Instrument::factory()->create(['is_borrowed' => false]);
        $second = Instrument::factory()->create(['is_borrowed' => false]);
        $movement = InstrumentMovement::factory()->create(['instrument_id' => $first->id, 'type' => 'pret', 'actual_return_on' => null]);
        $movement->update(['instrument_id' => $second->id]);
        $this->assertFalse($first->refresh()->is_borrowed);
        $this->assertTrue($second->refresh()->is_borrowed);
    }

    public function test_an_older_movement_does_not_override_the_latest(): void
    {
        $instrument = Instrument::factory()->create();
        InstrumentMovement::factory()->create(['instrument_id' => $instrument->id, 'type' => 'retour', 'moved_on' => '2026-01-02']);
        InstrumentMovement::factory()->create(['instrument_id' => $instrument->id, 'type' => 'pret', 'moved_on' => '2026-01-01', 'actual_return_on' => null]);
        $this->assertFalse($instrument->refresh()->is_borrowed);
    }

    public function test_quick_participant_edits_reject_invalid_values(): void
    {
        $activity = Activity::factory()->create();
        $member = Member::factory()->create();
        $activity->participants()->attach($member->id, ['status' => 'invite', 'hours' => 2]);
        $component = Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
            ->test(Participants::class, ['activity' => $activity]);
        $component->call('updateStatus', $member->id, 'unknown')->assertHasErrors(['status']);
        foreach (['-1', '1001', 'abc'] as $hours) {
            $component->call('updateHours', $member->id, $hours)->assertHasErrors(['hours']);
        }
        $this->assertDatabaseHas('activity_participants', ['activity_id' => $activity->id, 'member_id' => $member->id, 'status' => 'invite', 'hours' => 2]);
    }

    public function test_duplicate_user_email_is_a_validation_error(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($admin)->test(Form::class, ['user' => null])
            ->set('name', 'Duplicate')->set('username', 'duplicate')
            ->set('email', $admin->email)->set('password', 'password123')
            ->set('password_confirmation', 'password123')->call('save')->assertHasErrors(['email' => 'unique']);
    }

    public function test_editing_user_keeps_own_email_valid(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($admin)->test(Form::class, ['user' => $admin])
            ->call('save')->assertHasNoErrors();
    }

    public function test_duplicate_member_email_is_a_validation_error(): void
    {
        $member = Member::factory()->create();
        Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
            ->test(\App\Livewire\Members\Form::class, ['member' => null])
            ->set('first_name', 'Test')->set('last_name', 'Member')->set('email', $member->email)
            ->call('save')->assertHasErrors(['email' => 'unique']);
    }
}
