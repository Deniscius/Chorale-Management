<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Jobs\SendReminder;
use App\Livewire\Audit\Index;
use App\Livewire\Documents\Form;
use App\Livewire\Reminders\History;
use App\Models\AuditEvent;
use App\Models\Budget;
use App\Models\Document;
use App\Models\Instrument;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountWriter;
use App\Services\InstrumentLoans;
use App\Services\ReceiptLedger;
use App\Services\ReminderDispatcher;
use App\Services\Sms\SmsSender;
use App\Services\TransactionWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AuditHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_commission_download_policy_for_all_roles(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('audit.txt', 'Synthetic');
        $docs = [];
        foreach (Commission::cases() as $c) {
            $docs[] = Document::factory()->create(['commission' => $c, 'path' => 'audit.txt', 'mime_type' => 'text/plain', 'original_name' => 'audit.txt']);
        }
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            foreach ($docs as $doc) {
                $this->actingAs($user)->get(route('documents.download', $doc))->assertStatus((! $doc->commission->hasPrivateArchive() && in_array($doc->commission, Commission::forRole($role), true)) ? 200 : 403);
            }
        }
    }

    public function test_stale_document_form_cannot_move_an_unauthorized_source(): void
    {
        $a = User::factory()->role(UserRole::Admin)->create();
        $doc = Document::factory()->create(['commission' => Commission::Direction]);
        $test = Livewire::actingAs($a)->test(Form::class, ['document' => $doc]);
        $a->update(['role' => UserRole::Secretaire]);
        $test->set('commission', Commission::Secretariat->value)->assertForbidden();
        $this->assertSame(Commission::Direction, $doc->fresh()->commission);
    }

    public function test_other_admin_allows_departure_but_last_admin_is_protected(): void
    {
        $a = User::factory()->role(UserRole::Admin)->create();
        $b = User::factory()->role(UserRole::Admin)->create();
        $writer = app(AccountWriter::class);
        $writer->save($a, ['role' => 'choriste']);
        try {
            $writer->delete($b);
            $this->fail('Last admin');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('role', $e->errors());
        }$this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_audit_actor_differences_and_secret_exclusion(): void
    {
        $a = User::factory()->role(UserRole::Admin)->create();
        $u = User::factory()->role(UserRole::Choriste)->create();
        $this->actingAs($u);
        app(AccountWriter::class)->save($u, ['password' => 'SYNTHETIC-SECRET']);
        $this->actingAs($a);
        app(AccountWriter::class)->save($u, ['role' => 'secretaire']);
        $event = AuditEvent::where('subject_type', User::class)->where('subject_id', $u->id)->where('action', 'updated')->latest('id')->firstOrFail();
        $this->assertEquals($a->id, $event->actor_id);
        $this->assertSame('choriste', $event->changes['before']['role']);
        $this->assertSame('secretaire', $event->changes['after']['role']);
        $this->assertStringNotContainsString('password', json_encode($event->changes));
        $this->expectException(\LogicException::class);
        $event->delete();
    }

    public function test_audit_rolls_back_with_failed_financial_operation(): void
    {
        $count = AuditEvent::count();
        try {
            DB::transaction(function () {
                Transaction::factory()->create();
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }$this->assertSame($count, AuditEvent::count());
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_stock_rejects_overbooking_and_recovers_after_return(): void
    {
        $i = Instrument::factory()->create(['quantity' => 2]);
        $loans = app(InstrumentLoans::class);
        $first = $loans->save(null, ['instrument_id' => $i->id, 'quantity' => 2, 'type' => 'pret', 'moved_on' => today()]);
        try {
            $loans->save(null, ['instrument_id' => $i->id, 'quantity' => 1, 'type' => 'pret', 'moved_on' => today()]);
            $this->fail('Overbooking');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('quantity', $e->errors());
        }$this->assertSame(0, $i->availableQuantity());
        $loans->close($first->id);
        $this->assertSame(2, $i->availableQuantity());
    }

    public function test_quantity_cannot_be_reduced_below_loans(): void
    {
        $i = Instrument::factory()->create(['quantity' => 2]);
        app(InstrumentLoans::class)->save(null, ['instrument_id' => $i->id, 'quantity' => 2, 'type' => 'pret', 'moved_on' => today()]);
        Livewire::actingAs(User::factory()->role(UserRole::CommissionInstruments)->create())->test(\App\Livewire\Instruments\Form::class, ['instrument' => $i])->set('quantity', '1')->call('save')->assertHasErrors('quantity');
        $this->assertSame(2, $i->fresh()->quantity);
    }

    public function test_budget_uses_posted_expenses_and_opening_balance(): void
    {
        $b = Budget::factory()->create(['allocated_amount' => 10000, 'opening_realized_amount' => 4000, 'realized_amount' => 9000]);
        foreach ([['validee', 'depense', 1000], ['brouillon', 'depense', 3000], ['rejetee', 'depense', 2000], ['validee', 'recette', 6000]] as [$status,$type,$amount]) {
            Transaction::factory()->create(['budget_id' => $b->id, 'status' => $status, 'type' => $type, 'amount' => $amount]);
        }$this->assertSame(5000.0, $b->available());
        $this->assertSame(50.0, $b->consumptionRate());
        $this->assertSame(5000.0, Budget::withPostedExpenses()->find($b->id)->actualRealized());
    }

    private function fixture(): array
    {
        return [Rehearsal::factory()->create(['date' => today()->addDays(2), 'status' => 'planifiee']), Member::factory()->active()->create(['notify_email' => true, 'notify_sms' => false, 'email' => 'audit@example.invalid'])];
    }

    public function test_reminders_are_queued_once_without_synchronous_delivery(): void
    {
        Bus::fake([SendReminder::class]);
        Mail::fake();
        config()->set('chorale.reminders.queued', true);
        [$r] = $this->fixture();
        $d = app(ReminderDispatcher::class);
        $first = $d->dispatchForRehearsal($r);
        $second = $d->dispatchForRehearsal($r);
        $this->assertSame(1, $first['queued']);
        $this->assertSame(1, $second['skipped']);
        Bus::assertDispatchedTimes(SendReminder::class, 1);
        Mail::assertNothingSent();
    }

    public function test_delivery_rechecks_preferences_and_lease_token(): void
    {
        Bus::fake([SendReminder::class]);
        Mail::fake();
        config()->set('chorale.reminders.queued', true);
        [$r,$m] = $this->fixture();
        $d = app(ReminderDispatcher::class);
        $d->dispatchForRehearsal($r);
        $e = Reminder::firstOrFail();
        $this->assertSame('skipped', $d->deliver($e->id, 'obsolete'));
        $m->update(['notify_email' => false]);
        $this->assertSame('skipped', $d->deliver($e->id, $e->lease_token));
        $this->assertSame('ignore', $e->fresh()->status->value);
        Mail::assertNothingSent();
    }

    public function test_uncertain_send_is_reconciled_and_old_job_invalidated(): void
    {
        Bus::fake([SendReminder::class]);
        Mail::fake();
        config()->set('chorale.reminders.queued', true);
        [$r] = $this->fixture();
        $d = app(ReminderDispatcher::class);
        $d->dispatchForRehearsal($r);
        $e = Reminder::firstOrFail();
        $old = $e->lease_token;
        $e->update(['reserved_at' => now()->subHours(2), 'sending_at' => now()->subHours(2)]);
        $this->assertSame(1, $d->recoverStale());
        $this->assertSame('incertain', $e->fresh()->status->value);
        $d->resolve($e->id, 'retry');
        $e->refresh();
        $this->assertNotSame($old, $e->lease_token);
        $this->assertSame('skipped', $d->deliver($e->id, $old));
        $this->assertSame('sent', $d->deliver($e->id, $e->lease_token));
        $this->assertSame('skipped', $d->deliver($e->id, $e->lease_token));
        Mail::assertSentCount(1);
    }

    public function test_read_only_choriste_cannot_reconcile(): void
    {
        Livewire::actingAs(User::factory()->role(UserRole::Choriste)->create())->test(History::class)->call('resolve', 1, 'retry')->assertForbidden();
    }

    public function test_member_search_is_bounded_and_keeps_selection(): void
    {
        Member::factory()->count(50)->create();
        $m = Member::factory()->create(['first_name' => 'Sélection', 'last_name' => 'ZZZZ']);
        $test = Livewire::actingAs(User::factory()->role(UserRole::Tresorier)->create())->test(\App\Livewire\Contributions\Form::class, ['contribution' => null])->set('member_id', (string) $m->id);
        $this->assertLessThanOrEqual(31, $test->instance()->members->count());
        $this->assertTrue($test->instance()->members->contains('id', $m->id));
        $test->set('memberSearch', 'ZZZZ');
        $this->assertSame(1, $test->instance()->members->count());
    }

    public function test_audit_screen_is_reserved_to_admin_and_shows_filtered_history(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            $this->actingAs($user)->get('/administration/journal')->assertStatus($role === UserRole::Admin ? 200 : 403);
        }
        $admin = User::where('role', 'admin')->firstOrFail();
        Livewire::actingAs($admin)->test(Index::class)->set('action', 'created')->assertSee('Création');
    }

    public function test_saving_notes_without_payment_preserves_last_payment_date(): void
    {
        $ledger = app(ReceiptLedger::class);
        $c = $ledger->saveContribution(null, ['member_id' => Member::factory()->create()->id, 'year' => 2026, 'month' => 1, 'due_amount' => 5000, 'paid_amount' => 1500, 'paid_on' => '2026-01-10']);
        app(TransactionWriter::class)->transition($c->receipts()->first()->id, TransactionStatus::Validee);
        $ledger->saveContribution($c, ['paid_amount' => 1500, 'paid_on' => '2026-10-23', 'notes' => 'Note modifiée']);
        $this->assertSame('2026-01-10', $c->fresh()->paid_on->format('Y-m-d'));
        $this->assertSame(1, $c->receipts()->count());
    }

    public function test_global_disable_and_cancellation_stop_queued_delivery(): void
    {
        Bus::fake([SendReminder::class]);
        Mail::fake();
        config()->set('chorale.reminders.queued', true);
        [$r] = $this->fixture();
        $d = app(ReminderDispatcher::class);
        $d->dispatchForRehearsal($r);
        $entry = Reminder::firstOrFail();
        config()->set('chorale.reminders.enabled', false);
        $this->assertSame('skipped', $d->deliver($entry->id, $entry->lease_token));
        $this->assertSame('ignore', $entry->fresh()->status->value);
        config()->set('chorale.reminders.enabled', true);
        $entry->update(['status' => 'en_attente', 'sending_at' => null]);
        $r->update(['status' => 'annulee']);
        $this->assertSame('skipped', $d->deliver($entry->id, $entry->lease_token));
        Mail::assertNothingSent();
    }

    public function test_uncertain_transport_error_is_not_automatically_retried(): void
    {
        [$r, $member] = $this->fixture();
        $member->update(['notify_email' => false, 'notify_sms' => true, 'phone' => '+22800000000']);
        config()->set('chorale.reminders.sms_enabled', true);
        $sender = new class implements SmsSender
        {
            public int $calls = 0;

            public function send(string $to, string $message): bool
            {
                $this->calls++;
                throw new \RuntimeException('Timeout after possible delivery');
            }
        };
        $d = new ReminderDispatcher($sender);
        $this->assertSame(1, $d->dispatchForRehearsal($r)['failed']);
        $entry = Reminder::firstOrFail();
        $this->assertSame('incertain', $entry->status->value);
        $d->dispatchForRehearsal($r);
        $d->deliver($entry->id, $entry->lease_token, true);
        $this->assertSame(1, $sender->calls);
    }

    public function test_explicit_delivery_refusal_can_be_retried(): void
    {
        [$r, $member] = $this->fixture();
        $member->update(['notify_email' => false, 'notify_sms' => true, 'phone' => '+22800000000']);
        config()->set('chorale.reminders.sms_enabled', true);
        $sender = new class implements SmsSender
        {
            public bool $accepted = false;

            public function send(string $to, string $message): bool
            {
                return $this->accepted;
            }
        };
        $d = new ReminderDispatcher($sender);
        $this->assertSame(1, $d->dispatchForRehearsal($r)['failed']);
        $this->assertSame('echec', Reminder::first()->status->value);
        $sender->accepted = true;
        $this->assertSame(1, $d->dispatchForRehearsal($r)['sent']);
    }
}
