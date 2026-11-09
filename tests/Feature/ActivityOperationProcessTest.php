<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Livewire\Activities\Index as Activities;
use App\Livewire\Communications\Compose;
use App\Livewire\Settings\EmailDelivery;
use App\Livewire\Transactions\Form;
use App\Livewire\Transactions\Index;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Budget;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\MemberExpense;
use App\Models\Performance;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReceiptLedger;
use App\Services\TenantRegistration;
use App\Services\TransactionWriter;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

final class ActivityOperationProcessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operation_created_in_activity_context_keeps_its_link_until_validation(): void
    {
        $treasurer = User::factory()->role(UserRole::Tresorier)->create();
        $activity = Activity::factory()->create(['title' => 'Concert lié']);
        Livewire::actingAs($treasurer)->test(Index::class)->set('activityId', $activity->id)
            ->assertSee('Concert lié')->assertSee('Rapport complet de l’activité');
        Livewire::test(Form::class, ['activityId' => $activity->id])->assertSet('activity_id', (string) $activity->id)
            ->set('description', 'Location pour le concert')->set('type', 'depense')->set('amount', '12500')->call('save')->assertHasNoErrors();
        $entry = Transaction::query()->firstOrFail();
        $this->assertEquals($activity->id, $entry->activity_id);
        $this->assertSame(0, $activity->transactions()->validated()->count());
        app(TransactionWriter::class)->transition($entry->id, TransactionStatus::Validee);
        $this->assertEquals(12500, $activity->transactions()->validated()->sum('amount'));
        Livewire::test(Index::class)->call('edit', $entry->id)->assertSet('showForm', false);
    }

    public function test_a_draft_can_be_moved_to_another_activity_without_double_counting(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Tresorier)->create());
        $first = Activity::factory()->create();
        $second = Activity::factory()->create();
        $entry = Transaction::factory()->create(['activity_id' => $first->id, 'status' => 'brouillon']);
        app(TransactionWriter::class)->save($entry, ['activity_id' => $second->id]);
        $this->assertSame(0, $first->transactions()->count());
        $this->assertSame(1, $second->transactions()->count());
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_financial_history_prevents_activity_deletion(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $activity = Activity::factory()->create();
        Transaction::factory()->create(['activity_id' => $activity->id, 'status' => 'brouillon']);
        Livewire::actingAs($admin)->test(Activities::class)->call('delete', $activity->id)->assertHasErrors('operation');
        $this->assertDatabaseHas('activities', ['id' => $activity->id]);
        $this->assertDatabaseHas('transactions', ['activity_id' => $activity->id]);
        $withFee = Activity::factory()->create();
        Contribution::factory()->create(['activity_id' => $withFee->id, 'member_id' => Member::factory()->create()->id]);
        Livewire::test(Activities::class)->call('delete', $withFee->id)->assertHasErrors('operation');
        $withAdvance = Activity::factory()->create();
        MemberExpense::create(['activity_id' => $withAdvance->id, 'member_id' => Member::factory()->create()->id,
            'incurred_on' => today(), 'description' => 'Avance à examiner', 'amount' => 1000]);
        Livewire::test(Activities::class)->call('delete', $withAdvance->id)->assertHasErrors('operation');
        $empty = Activity::factory()->create();
        Livewire::test(Activities::class)->call('delete', $empty->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('activities', ['id' => $empty->id]);
    }

    public function test_email_diagnostic_distinguishes_accounts_from_members_without_accounts(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $member = Member::factory()->active()->create(['email' => 'contact@example.test']);
        User::factory()->role(UserRole::Choriste)->create(['member_id' => $member->id]);
        Member::factory()->active()->create(['email' => 'sans-compte@example.test']);
        Member::factory()->active()->create(['email' => null]);
        Member::factory()->create(['status' => 'externe', 'email' => 'externe@example.test']);
        Livewire::actingAs($admin)->test(EmailDelivery::class)->assertSet('coverage', [
            'active' => 3, 'automatic' => 1, 'without_account' => 1, 'missing_address' => 1,
        ])->assertSee('pas une preuve de réception');
    }

    public function test_foreign_budget_member_and_performance_cannot_be_attached_to_an_operation(): void
    {
        config(['tenancy.enabled' => true]);
        Notification::fake();
        $registration = app(TenantRegistration::class);
        $alpha = $registration->create(['organization' => 'Alpha', 'name' => 'Alpha', 'username' => 'alpha-flow', 'email' => 'alpha-flow@example.test', 'password' => 'SecretChorale2026']);
        $beta = $registration->create(['organization' => 'Beta', 'name' => 'Beta', 'username' => 'beta-flow', 'email' => 'beta-flow@example.test', 'password' => 'SecretChorale2026']);
        $context = app(TenantContext::class);
        $this->actingAs($beta);
        $foreign = $context->run($beta->tenant_id, fn () => [
            'budget_id' => Budget::factory()->create()->id,
            'member_id' => Member::factory()->create()->id,
            'performance_id' => Performance::factory()->create()->id,
        ]);
        $this->actingAs($alpha);
        $context->run($alpha->tenant_id, function () use ($foreign): void {
            $entry = Transaction::factory()->create(['status' => 'brouillon']);
            foreach ($foreign as $field => $id) {
                try {
                    app(TransactionWriter::class)->save($entry, [$field => $id]);
                    $this->fail('A foreign record must be rejected.');
                } catch (ModelNotFoundException) {
                    $this->assertNull($entry->fresh()->getAttribute($field));
                }
            }
        });
    }

    public function test_activity_messages_include_external_helpers_without_broadcasting_to_the_whole_choir(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $external = Member::factory()->create(['status' => 'externe']);
        $activity = Activity::factory()->create();
        $activity->participantRecords()->create(['member_id' => $external->id, 'status' => 'invite']);
        Livewire::actingAs($admin)->test(Compose::class)
            ->set('audience', 'activity')->set('activityId', (string) $activity->id)
            ->assertSee($activity->title)->assertCount('candidates', 1)
            ->set('audience', 'chorale')->assertCount('candidates', 0);
    }

    public function test_a_donation_receipt_can_be_classified_without_editing_or_duplicating_its_amount(): void
    {
        $treasurer = User::factory()->role(UserRole::Tresorier)->create();
        $this->actingAs($treasurer);
        $activity = Activity::factory()->create();
        $account = Account::factory()->create(['is_active' => true]);
        $donation = app(ReceiptLedger::class)->saveDonation(null, ['amount' => 2000, 'donated_on' => today(), 'donor_type' => 'anonyme', 'donation_type' => 'offrande_messe']);
        Livewire::test(Index::class)->call('classify', $donation->transaction_id)
            ->set('classificationActivity', (string) $activity->id)->set('classificationAccount', (string) $account->id)
            ->call('saveClassification')->assertHasNoErrors()->assertSet('showClassification', false);
        $this->assertDatabaseHas('transactions', ['id' => $donation->transaction_id, 'activity_id' => $activity->id, 'account_id' => $account->id, 'amount' => 2000]);
        app(ReceiptLedger::class)->saveDonation($donation, ['amount' => 2500]);
        $this->assertDatabaseHas('transactions', ['id' => $donation->transaction_id, 'activity_id' => $activity->id, 'account_id' => $account->id, 'amount' => 2500]);
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_classification_respects_contribution_origin_and_a_stale_validated_operation(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Tresorier)->create());
        $activity = Activity::factory()->create();
        $other = Activity::factory()->create();
        $contribution = Contribution::factory()->create(['activity_id' => $activity->id, 'member_id' => Member::factory()->create()->id]);
        $entry = Transaction::factory()->create(['contribution_id' => $contribution->id, 'activity_id' => $activity->id, 'status' => 'brouillon']);
        $component = Livewire::test(Index::class)->call('classify', $entry->id)
            ->set('classificationActivity', (string) $other->id)->call('saveClassification')->assertHasErrors('classificationActivity');
        $this->assertEquals($activity->id, $entry->fresh()->activity_id);
        app(TransactionWriter::class)->transition($entry->id, TransactionStatus::Validee);
        $component->set('classificationActivity', (string) $activity->id)->call('saveClassification')->assertHasErrors('classificationActivity');
    }

    public function test_read_only_accounting_cannot_classify_operations(): void
    {
        $entry = Transaction::factory()->create(['status' => 'brouillon']);
        Livewire::actingAs(User::factory()->role(UserRole::Choriste)->create())->test(Index::class)->assertForbidden();
    }
}
