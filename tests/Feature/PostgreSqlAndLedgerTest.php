<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Livewire\Contributions\Form as ContributionForm;
use App\Livewire\Donations\Index as DonationsIndex;
use App\Livewire\Rehearsals\Form;
use App\Livewire\Transactions\Index as TransactionsIndex;
use App\Models\Contribution;
use App\Models\Correspondence;
use App\Models\Donation;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ReceiptLedger;
use App\Services\ReferenceSequence;
use App\Services\TransactionWriter;
use App\Support\CalendarYears;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PostgreSqlAndLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_year_filters_are_portable_and_sorted(): void
    {
        Donation::factory()->create(['donated_on' => '2024-06-01']);
        Donation::factory()->count(2)->create(['donated_on' => '2026-06-01']);
        $this->assertSame([2026, 2024], CalendarYears::for(new Donation, 'donated_on'));
        Correspondence::factory()->create(['correspondence_date' => '2025-01-01']);
        $this->assertSame([2025], CalendarYears::for(new Correspondence, 'correspondence_date'));
    }

    public function test_search_is_case_insensitive(): void
    {
        Donation::factory()->create(['donor_name' => 'MARIE Exemple']);
        Livewire::actingAs(User::factory()->role(UserRole::Tresorier)->create())
            ->test(DonationsIndex::class)->set('search', 'marie')->assertSee('MARIE Exemple');
    }

    public function test_references_survive_deletions_and_more_than_9999_entries(): void
    {
        $entry = Transaction::factory()->create(['reference' => 'OP/2026/10000']);
        Transaction::factory()->create(['reference' => 'OP/2026/9999']);
        $generator = app(ReferenceSequence::class);
        $this->assertSame('OP/2026/10001', $generator->next('transactions', 'OP', 2026));
        $entry->delete();
        $this->assertSame('OP/2026/10002', $generator->next('transactions', 'OP', 2026));
    }

    public function test_new_donation_prepares_one_draft_and_updates_it(): void
    {
        $ledger = app(ReceiptLedger::class);
        $donation = $ledger->saveDonation(null, ['amount' => '2500.50', 'donated_on' => '2026-01-02', 'donor_type' => 'anonyme', 'donation_type' => 'offrande_messe']);
        $this->assertDatabaseHas('transactions', ['id' => $donation->transaction_id, 'amount' => '2500.50', 'status' => 'brouillon']);
        $ledger->saveDonation($donation, ['amount' => '3000.25']);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame('3000.25', $donation->transaction->refresh()->amount);
        $ledger->deleteDonation($donation->id);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_validated_donation_cannot_be_edited(): void
    {
        $ledger = app(ReceiptLedger::class);
        $donation = $ledger->saveDonation(null, ['amount' => 1000, 'donated_on' => today(), 'donor_type' => 'anonyme', 'donation_type' => 'offrande_messe']);
        app(TransactionWriter::class)->transition($donation->transaction_id, TransactionStatus::Validee);
        $this->expectException(ValidationException::class);
        $ledger->saveDonation($donation, ['amount' => 500]);
    }

    public function test_partial_contribution_then_final_payment_creates_only_the_difference(): void
    {
        $ledger = app(ReceiptLedger::class);
        $member = Member::factory()->create();
        $contribution = $ledger->saveContribution(null, ['member_id' => $member->id, 'year' => 2026, 'month' => 2, 'due_amount' => '5000.00', 'paid_amount' => '1500.25']);
        $this->assertSame('partiel', $contribution->status->value);
        $entry = $contribution->receipts()->firstOrFail();
        app(TransactionWriter::class)->transition($entry->id, TransactionStatus::Validee);
        $contribution = $ledger->saveContribution($contribution, ['paid_amount' => '5000.00']);
        $this->assertSame('payee', $contribution->status->value);
        $this->assertDatabaseHas('transactions', ['contribution_id' => $contribution->id, 'status' => 'brouillon', 'amount' => '3499.75']);
        $ledger->saveContribution($contribution, ['paid_amount' => '5000.00']);
        $this->assertSame(2, $contribution->receipts()->count());
        $this->assertEquals(5000, $contribution->receipts()->sum('amount'));
    }

    public function test_payment_below_validated_amount_is_rejected(): void
    {
        $ledger = app(ReceiptLedger::class);
        $member = Member::factory()->create();
        $contribution = $ledger->saveContribution(null, ['member_id' => $member->id, 'year' => 2026, 'month' => 2, 'due_amount' => 5000, 'paid_amount' => 1500]);
        app(TransactionWriter::class)->transition($contribution->receipts()->first()->id, TransactionStatus::Validee);
        $this->expectException(ValidationException::class);
        $ledger->saveContribution($contribution, ['paid_amount' => 1000]);
    }

    public function test_overpayment_and_duplicate_period_show_field_errors(): void
    {
        $member = Member::factory()->create();
        Contribution::factory()->create(['member_id' => $member->id, 'year' => 2026, 'month' => 1]);
        Livewire::actingAs(User::factory()->role(UserRole::Tresorier)->create())
            ->test(ContributionForm::class, ['contribution' => null])
            ->set('member_id', (string) $member->id)->set('year', '2026')->set('month', '1')
            ->set('due_amount', '1000')->set('paid_amount', '2000')->call('save')
            ->assertHasErrors(['month', 'paid_amount']);
    }

    public function test_posted_transaction_is_protected_even_from_a_stale_form(): void
    {
        $entry = Transaction::factory()->create(['status' => 'brouillon']);
        $stale = clone $entry;
        $writer = app(TransactionWriter::class);
        $writer->transition($entry->id, TransactionStatus::Validee);
        $this->expectException(ValidationException::class);
        $writer->save($stale, ['amount' => 1]);
    }

    public function test_posted_transaction_cannot_be_deleted(): void
    {
        $entry = Transaction::factory()->create(['status' => 'validee']);
        Livewire::actingAs(User::factory()->role(UserRole::Tresorier)->create())
            ->test(TransactionsIndex::class)->call('delete', $entry->id)->assertHasErrors(['operation']);
        $this->assertDatabaseHas('transactions', ['id' => $entry->id]);
    }

    public function test_historical_payment_is_not_duplicated_automatically(): void
    {
        $contribution = Contribution::factory()->create(['member_id' => Member::factory()->create()->id, 'due_amount' => 5000, 'paid_amount' => 1000]);
        try {
            app(ReceiptLedger::class)->saveContribution($contribution, ['paid_amount' => 5000]);
            $this->fail('A historical receipt requires reconciliation.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('transactions', 0);
            $this->assertSame('1000.00', $contribution->refresh()->paid_amount);
        }
    }

    public function test_in_kind_donation_does_not_create_cash(): void
    {
        $donation = app(ReceiptLedger::class)->saveDonation(null, ['amount' => 5000, 'donated_on' => today(), 'donor_type' => 'anonyme', 'donation_type' => 'don_materiel']);
        $donation = app(ReceiptLedger::class)->saveDonation($donation, ['amount' => 6000]);
        $this->assertNull($donation->transaction_id);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_rehearsal_time_with_seconds_remains_editable(): void
    {
        $rehearsal = Rehearsal::factory()->create(['starts_at' => '18:00:00', 'ends_at' => '20:00:00']);
        Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())
            ->test(Form::class, ['rehearsal' => $rehearsal])
            ->assertSet('starts_at', '18:00')->assertSet('ends_at', '20:00')
            ->call('save')->assertHasNoErrors();
    }

    public function test_receipt_preserves_the_selected_payment_method(): void
    {
        $ledger = app(ReceiptLedger::class);
        $donation = $ledger->saveDonation(null, ['amount' => 5000, 'donated_on' => today(), 'donor_type' => 'anonyme', 'donation_type' => 'offrande_messe', 'payment_method' => 'virement']);
        $this->assertSame('virement', $donation->transaction->payment_method->value);
        $member = Member::factory()->create();
        $contribution = $ledger->saveContribution(null, ['member_id' => $member->id, 'year' => 2026, 'month' => 3, 'due_amount' => 5000, 'paid_amount' => 2000, 'payment_method' => 'virement']);
        $this->assertSame('virement', $contribution->receipts()->first()->payment_method->value);
    }
}
