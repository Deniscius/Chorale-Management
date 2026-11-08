<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Livewire\Accounting\Accounts;
use App\Livewire\Accounting\Dashboard;
use App\Livewire\Accounting\MemberExpenses;
use App\Livewire\Transactions\Form as TransactionForm;
use App\Livewire\Transactions\Index as Transactions;
use App\Models\Account;
use App\Models\Activity;
use App\Models\Contribution;
use App\Models\ManagementYear;
use App\Models\Member;
use App\Models\MemberExpense;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ActivityParticipationFee;
use App\Services\ManagementYearContext;
use App\Services\MemberExpenseWriter;
use App\Services\ReceiptLedger;
use App\Services\TenantRegistration;
use App\Services\TransactionWriter;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class MemberExpensesTest extends TestCase
{
    use RefreshDatabase;

    private function treasurer(): User
    {
        $user = User::factory()->role(UserRole::Tresorier)->create();
        $this->actingAs($user);

        return $user;
    }

    private function advance(?Activity $activity = null): MemberExpense
    {
        return app(MemberExpenseWriter::class)->create([
            'member_id' => Member::factory()->create()->id, 'activity_id' => $activity?->id,
            'incurred_on' => today()->subDay()->toDateString(), 'description' => 'Transport payé personnellement',
            'amount' => '10000.50', 'claimed_on' => today()->toDateString(), 'evidence_reference' => 'FACT-42',
        ]);
    }

    private function payment(array $overrides = []): array
    {
        return $overrides + ['amount' => '4000.25', 'transaction_date' => today()->toDateString(),
            'account_id' => Account::factory()->create(['is_active' => true])->id,
            'payment_method' => 'especes', 'reimbursement_key' => (string) Str::uuid()];
    }

    private function assertValidation(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail('Une erreur de validation était attendue.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_partial_reimbursements_are_linked_once_and_do_not_duplicate_the_expense(): void
    {
        $user = $this->treasurer();
        $activity = Activity::factory()->create(['attendance_finalized_at' => now()]);
        $expense = $this->advance($activity);
        $writer = app(MemberExpenseWriter::class);
        $this->assertDatabaseCount('transactions', 0);
        $writer->decide($expense->id, 'approved', 'Bureau du jour : remboursement accepté.');
        $this->assertDatabaseCount('transactions', 0);
        $payment = $this->payment();
        $first = $writer->reimburse($expense->id, $payment);
        $repeat = $writer->reimburse($expense->id, $payment);
        $this->assertSame($first->id, $repeat->id);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertEquals(6000.25, $expense->fresh()->outstandingAmount());
        $this->assertDatabaseHas('transactions', ['id' => $first->id, 'member_expense_id' => $expense->id,
            'activity_id' => $activity->id, 'member_id' => $expense->member_id,
            'type' => 'depense', 'status' => 'validee', 'validated_by' => $user->id]);
        $writer->reimburse($expense->id, $this->payment(['amount' => '6000.25']));
        $this->assertEquals(0, $expense->fresh()->outstandingAmount());
        $this->assertEquals(10000.50, Transaction::query()->validated()->sum('amount'));
        Livewire::actingAs($user)->test(Transactions::class)->call('delete', $first->id)->assertHasErrors('operation');
        $report = $this->get(route('attendance.report', ['activite', $activity->id]));
        $report->assertOk()->assertSee('Avances des choristes et remboursements')->assertSee('FACT-42');
        $this->assertEquals(10000.50, $report->viewData('finance')['expenses']);
        $this->assertEquals(0, $report->viewData('finance')['advances']->first()->outstandingAmount());
        $dashboard = Livewire::actingAs($user)->test(Dashboard::class);
        $this->assertEquals(0, $dashboard->instance()->advances['outstanding']);
    }

    public function test_payments_require_approval_an_active_account_and_cannot_exceed_the_balance(): void
    {
        $this->treasurer();
        $expense = $this->advance();
        $writer = app(MemberExpenseWriter::class);
        $payment = $this->payment();
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $payment), 'operation');
        $this->assertValidation(fn () => $writer->decide($expense->id, 'approved', '   '), 'decision_note');
        $writer->decide($expense->id, 'rejected', 'Justificatif insuffisant.');
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $payment), 'operation');
        $writer->decide($expense->id, 'approved', 'Justificatif reçu et accepté par le bureau.');
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $this->payment(['amount' => '10000.51'])), 'amount');
        $inactive = Account::factory()->create(['is_active' => false]);
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $this->payment(['account_id' => $inactive->id])), 'account_id');
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $this->payment(['amount' => '0'])), 'amount');
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $this->payment(['amount' => '1.001'])), 'amount');
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $this->payment(['transaction_date' => today()->subDays(2)->toDateString()])), 'transaction_date');
        $this->assertDatabaseCount('transactions', 0);
        $writer->reimburse($expense->id, $payment);
        $this->assertValidation(fn () => $writer->decide($expense->id, 'rejected', 'Annuler'), 'operation');
        $this->assertValidation(fn () => $writer->reimburse($expense->id, $this->payment(['amount' => '6000.26'])), 'amount');
        $this->assertDatabaseCount('transactions', 1);
    }

    public function test_accounting_can_record_a_claim_decide_and_record_a_payment_from_the_screen(): void
    {
        $user = $this->treasurer();
        $member = Member::factory()->create();
        $activity = Activity::factory()->create();
        $account = Account::factory()->create(['is_active' => true]);
        $component = Livewire::actingAs($user)->test(MemberExpenses::class)
            ->call('create')->set('member_id', (string) $member->id)->set('activity_id', (string) $activity->id)
            ->set('description', 'Achat de partitions')->set('amount', '2500.50')
            ->call('save')->assertHasNoErrors();
        $expense = MemberExpense::query()->sole();
        $component->call('selectExpense', $expense->id)->assertSee('Achat de partitions')
            ->call('recordClaim', $expense->id)->set('decision_note', 'Accord du bureau')
            ->call('decide')->assertHasNoErrors()
            ->set('account_id', (string) $account->id)->set('paymentAmount', '500.25')
            ->call('reimburse')->assertHasNoErrors()->assertSet('selectedId', null);
        $this->assertNotNull($expense->fresh()->claimed_on);
        $this->assertEquals(2000.25, $expense->fresh()->outstandingAmount());
        $component->call('selectExpense', $expense->id)->assertSee('500,25')->call('closeDetail')->assertSet('selectedId', null);
        $this->get(route('accounting.member-expenses'))->assertOk()->assertSee('Achat de partitions');
        $this->get(route('accounting.dashboard'))->assertOk()->assertSee('2 000,25 FCFA');
    }

    public function test_existing_accounts_can_be_renamed_and_deactivated_without_losing_history(): void
    {
        $user = $this->treasurer();
        $account = Account::factory()->create(['code' => '501', 'name' => 'Caisse de base', 'is_active' => true]);
        $transaction = Transaction::factory()->recette()->create(['account_id' => $account->id]);
        Livewire::actingAs($user)->test(Accounts::class)->call('edit', $account->id)
            ->set('name', 'Caisse principale')->set('is_active', false)->call('save')->assertHasNoErrors();
        $this->assertEquals($account->id, $transaction->fresh()->account_id);
        $this->assertFalse($account->fresh()->is_active);
        $this->get(route('transactions.index'))->assertOk()->assertSee('Caisse principale');
        $this->get(route('accounting.accounts'))->assertOk()->assertSee('Désactivé');
        Livewire::actingAs($user)->test(Accounts::class)->call('create')->set('code', '501')->set('name', 'Doublon')
            ->call('save')->assertHasErrors('code');
        Livewire::actingAs($user)->test(Accounts::class)->call('create')->set('code', '504')->set('name', 'Mobile Money')
            ->call('save')->assertHasNoErrors();
        Livewire::actingAs($user)->test(TransactionForm::class)
            ->set('description', 'Nouvelle dépense')->set('type', 'depense')->set('amount', '500')
            ->set('account_id', (string) $account->id)->call('save')->assertHasErrors('account_id');
        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('audit_events', ['subject_type' => Account::class, 'subject_id' => $account->id, 'action' => 'updated']);
    }

    public function test_activity_and_account_filters_keep_all_row_information_and_matching_totals(): void
    {
        $user = $this->treasurer();
        $activity = Activity::factory()->create(['title' => 'Concert lié']);
        $member = Member::factory()->create(['first_name' => 'Alice', 'last_name' => 'Mensah']);
        $account = Account::factory()->create(['name' => 'Caisse mobile']);
        Transaction::factory()->depense()->create(['activity_id' => $activity->id, 'member_id' => $member->id,
            'account_id' => $account->id, 'amount' => 1234, 'description' => 'Ligne du concert']);
        Transaction::factory()->depense()->create(['activity_id' => $activity->id, 'amount' => 7777, 'description' => 'Autre compte']);
        Transaction::factory()->depense()->create(['amount' => 8888, 'description' => 'Autre activité']);
        $component = Livewire::actingAs($user)->test(Transactions::class)->set('activityId', $activity->id)
            ->set('accountId', $account->id)->assertSee('Concert lié')->assertSee('Alice Mensah')
            ->assertSee('Caisse mobile')->assertDontSee('Autre compte')->assertDontSee('Autre activité');
        $this->assertEquals(1234, $component->instance()->stats['depenses']);
        $this->assertEquals(1, $component->instance()->stats['total']);
        $component->call('resetFilters')->assertSet('activityId', null)->assertSet('accountId', null);
        Livewire::actingAs($user)->test(TransactionForm::class, ['activityId' => $activity->id])
            ->assertSet('activity_id', (string) $activity->id);
        $this->get(route('activities.index'))->assertOk()->assertSee(route('transactions.index', ['activite' => $activity->id]), false);
    }

    public function test_non_accounting_roles_cannot_read_or_modify_advances_and_accounts(): void
    {
        foreach ([UserRole::Secretaire, UserRole::Choriste] as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get(route('accounting.member-expenses'))->assertForbidden();
            $this->get(route('accounting.accounts'))->assertForbidden();
            Livewire::actingAs($user)->test(MemberExpenses::class)->assertForbidden();
            Livewire::actingAs($user)->test(Accounts::class)->assertForbidden();
        }
    }

    public function test_advances_and_accounts_are_isolated_between_choirs(): void
    {
        config(['tenancy.enabled' => true]);
        Notification::fake();
        $registration = app(TenantRegistration::class);
        $alpha = $registration->create(['organization' => 'Alpha', 'name' => 'Alpha', 'username' => 'alpha', 'email' => 'alpha@example.test', 'password' => 'SecretChorale2026']);
        $beta = $registration->create(['organization' => 'Beta', 'name' => 'Beta', 'username' => 'beta', 'email' => 'beta@example.test', 'password' => 'SecretChorale2026']);
        $context = app(TenantContext::class);
        $this->actingAs($beta);
        [$expense, $foreignAccount] = $context->run($beta->tenant_id, function () {
            return [$this->advance(), Account::factory()->create(['code' => '9999-TEST', 'is_active' => true])];
        });
        $this->actingAs($alpha);
        $context->run($alpha->tenant_id, function () use ($expense, $foreignAccount, $alpha): void {
            Livewire::actingAs($alpha)->test(Accounts::class)->call('create')->set('code', '9999-TEST')->set('name', 'Caisse Alpha')
                ->call('save')->assertHasNoErrors();
            Livewire::actingAs($alpha)->test(Accounts::class)->call('edit', $foreignAccount->id)->assertNotFound();
            Livewire::actingAs($alpha)->test(MemberExpenses::class)->call('selectExpense', $expense->id)->assertNotFound();
            $own = $this->advance();
            app(MemberExpenseWriter::class)->decide($own->id, 'approved', 'Accord Alpha');
            $this->assertValidation(fn () => app(MemberExpenseWriter::class)->reimburse($own->id, $this->payment(['account_id' => $foreignAccount->id])), 'account_id');
            $this->assertDatabaseCount('transactions', 0);
        });
    }

    public function test_participation_receipts_inherit_the_activity_and_historical_links_are_restored(): void
    {
        $user = $this->treasurer();
        $activity = Activity::factory()->create([
            'participation_fee_enabled' => true, 'participation_fee_amount' => 5000,
        ]);
        $member = Member::factory()->active()->create();
        app(ActivityParticipationFee::class)->createFor($activity, [$member->id]);
        $contribution = Contribution::query()->where('activity_id', $activity->id)->sole();
        app(ReceiptLedger::class)->saveContribution($contribution, [
            'paid_amount' => 2000, 'paid_on' => today()->toDateString(),
        ]);
        $receipt = Transaction::query()->where('contribution_id', $contribution->id)->sole();
        $this->assertSame($activity->id, $receipt->activity_id);
        app(TransactionWriter::class)->transition($receipt->id, TransactionStatus::Validee);
        $receipt->refresh();
        $before = $receipt->getAttributes();
        DB::table('transactions')->where('id', $receipt->id)->update(['activity_id' => null]);
        $unrelated = Transaction::factory()->depense()->create(['activity_id' => null]);
        $migration = require database_path('migrations/2026_11_08_000002_link_activity_participation_receipts.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, $receipt->fresh()->getAttributes());
        $this->assertNull($unrelated->fresh()->activity_id);
        Livewire::actingAs($user)->test(Transactions::class)->set('activityId', $activity->id)
            ->assertSee($receipt->reference);
    }

    public function test_old_approved_advances_remain_due_in_a_new_working_year(): void
    {
        $user = $this->treasurer();
        $year = ManagementYear::query()->create([
            'type' => 'civile', 'label' => (string) today()->year,
            'starts_on' => today()->startOfYear(), 'ends_on' => today()->endOfYear(), 'is_active' => true,
        ]);
        app(ManagementYearContext::class)->choose($year->id);
        $expense = app(MemberExpenseWriter::class)->create([
            'member_id' => Member::factory()->create()->id,
            'incurred_on' => today()->subYears(2)->toDateString(), 'description' => 'Ancienne avance', 'amount' => '10000.50',
        ]);
        app(MemberExpenseWriter::class)->decide($expense->id, 'approved', 'Ancienne dette reconnue par le bureau.');
        $transaction = app(MemberExpenseWriter::class)->reimburse($expense->id, $this->payment());
        $dashboard = Livewire::actingAs($user)->test(Dashboard::class);
        $this->assertEquals(6000.25, $dashboard->instance()->advances['outstanding']);
        $this->assertEquals(4000.25, $dashboard->instance()->figures['expense']);
        $this->get(route('transactions.index'))->assertOk()->assertSee($transaction->reference)->assertSee('4 000,25');
    }
}
