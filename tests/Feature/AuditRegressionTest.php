<?php

namespace Tests\Feature;

use App\Enums\Commission;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Livewire\Contributions\Index;
use App\Livewire\Instruments\Ensembles;
use App\Livewire\Instruments\Maintenances;
use App\Livewire\Instruments\Movements;
use App\Livewire\Users\Form;
use App\Models\Budget;
use App\Models\Document;
use App\Models\Donation;
use App\Models\Instrument;
use App\Models\InstrumentMovement;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\AiClient;
use App\Services\ChoraleAssistant;
use App\Services\ReceiptLedger;
use App\Services\ReminderDispatcher;
use App\Services\TransactionWriter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_direction_document_direct_download_is_denied_to_choriste(): void
    {
        Storage::fake('local');
        $doc = Document::factory()->create(['commission' => Commission::Direction, 'path' => 'documents/audit.txt', 'original_name' => 'audit.txt', 'mime_type' => 'text/plain']);
        Storage::disk('local')->put($doc->path, 'AUDIT SYNTHETIQUE');
        $this->actingAs(User::factory()->role(UserRole::Choriste)->create());
        $this->get('/documents?commission=commission_direction')->assertOk()->assertDontSee($doc->title);
        $this->get(route('documents.download', $doc))->assertForbidden();
    }

    public function test_last_admin_cannot_demote_himself(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create();
        Livewire::actingAs($user)->test(Form::class, ['user' => $user])->set('role', 'choriste')->call('save')->assertHasErrors(['role']);
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_last_admin_cannot_delete_own_profile(): void
    {
        $user = User::factory()->role(UserRole::Admin)->create(['password' => bcrypt('audit-password')]);
        $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'audit-password'])->assertSessionHasErrors(['role'], null, 'userDeletion');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_latest_return_keeps_older_open_loan_visible(): void
    {
        $instrument = Instrument::factory()->create(['quantity' => 2]);
        $old = InstrumentMovement::factory()->create(['instrument_id' => $instrument->id, 'type' => 'pret', 'moved_on' => '2026-01-01', 'actual_return_on' => null]);
        $new = InstrumentMovement::factory()->create(['instrument_id' => $instrument->id, 'type' => 'pret', 'moved_on' => '2026-01-02', 'actual_return_on' => null]);
        $new->update(['actual_return_on' => '2026-01-03']);
        $this->assertNull($old->refresh()->actual_return_on);
        $this->assertTrue($instrument->refresh()->is_borrowed);
        $this->assertSame(1, $instrument->availableQuantity());
    }

    public function test_quick_close_rejects_return_before_checkout(): void
    {
        $movement = InstrumentMovement::factory()->create(['instrument_id' => Instrument::factory()->create()->id, 'type' => 'pret', 'moved_on' => today()->addDays(5), 'actual_return_on' => null]);
        Livewire::actingAs(User::factory()->role(UserRole::CommissionInstruments)->create())->test(Movements::class)->call('close', $movement->id)->assertHasErrors(['operation']);
        $this->assertNull($movement->refresh()->actual_return_on);
    }

    public function test_new_contribution_receipt_uses_complement_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 23));
        $ledger = app(ReceiptLedger::class);
        $c = $ledger->saveContribution(null, ['member_id' => Member::factory()->create()->id, 'year' => 2026, 'month' => 1, 'due_amount' => 5000, 'paid_amount' => 1500, 'paid_on' => '2026-01-10']);
        app(TransactionWriter::class)->transition($c->receipts()->first()->id, TransactionStatus::Validee);
        Livewire::actingAs(User::factory()->role(UserRole::Tresorier)->create())->test(Index::class)->call('markAsPaid', $c->id)->assertHasNoErrors();
        $entry = $c->receipts()->where('status', 'brouillon')->firstOrFail();
        $this->assertSame('2026-10-23', $entry->transaction_date->format('Y-m-d'));
        $this->assertEquals(3500, $entry->amount);
        $this->travelBack();
    }

    public function test_anonymous_donation_removes_member_identity(): void
    {
        $member = Member::factory()->create();
        Livewire::actingAs(User::factory()->role(UserRole::Tresorier)->create())->test(\App\Livewire\Donations\Form::class, ['donation' => null])->set('member_id', (string) $member->id)->set('donor_type', 'anonyme')->set('amount', '1000')->call('save')->assertHasNoErrors();
        $donation = Donation::firstOrFail();
        $this->assertNull($donation->donor_name);
        $this->assertNull($donation->member_id);
        $this->assertNull($donation->transaction->member_id);
    }

    public function test_ai_prompt_excludes_contacts_in_free_notes(): void
    {
        $client = new class implements AiClient
        {
            public string $prompt = '';

            public function complete(string $system, string $prompt): string
            {
                $this->prompt = $prompt;

                return 'Test';
            }
        };
        (new ChoraleAssistant($client))->generateRehearsalReport(Rehearsal::factory()->create(['notes' => 'Contact fictif audit@example.invalid, +228 00000000']));
        $this->assertStringNotContainsString('audit@example.invalid', $client->prompt);
        $this->assertStringNotContainsString('+228 00000000', $client->prompt);
    }

    public function test_revoked_module_role_cannot_refresh_finance_component(): void
    {
        $user = User::factory()->role(UserRole::Tresorier)->create();
        Transaction::factory()->create(['description' => 'AUDIT SECRET FINANCIER']);
        $component = Livewire::actingAs($user)->test(\App\Livewire\Transactions\Index::class);
        $user->update(['role' => UserRole::Choriste]);
        $this->actingAs($user->fresh())->get('/transactions')->assertForbidden();
        $component->call('resetFilters')->assertForbidden();
    }

    public function test_database_rejects_two_accounts_for_same_member(): void
    {
        $member = Member::factory()->create();
        User::factory()->create(['member_id' => $member->id]);
        $this->expectException(UniqueConstraintViolationException::class);
        User::factory()->create(['member_id' => $member->id]);
    }

    public function test_login_whitespace_is_normalized_by_http_middleware(): void
    {
        User::factory()->create(['username' => 'auditadmin']);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => 'auditadmin', 'password' => 'wrong'])->assertSessionHasErrors('login');
        }
        $this->post('/login', ['login' => ' auditadmin ', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->assertTrue(RateLimiter::tooManyAttempts(hash('sha256', 'auditadmin|127.0.0.1'), 5));
        $this->assertSame(0, RateLimiter::attempts(hash('sha256', ' auditadmin |127.0.0.1')));
    }

    public function test_all_nine_roles_route_matrix(): void
    {
        $screens = [
            '/dashboard' => null, '/membres' => 'members', '/oeuvres' => 'liturgique', '/repetitions' => 'liturgique', '/celebrations' => 'liturgique', '/temps-liturgiques' => 'liturgique', '/rappels' => 'liturgique',
            '/instruments' => 'instruments', '/instruments/mouvements' => 'instruments', '/instruments/maintenance' => 'instruments', '/instruments/ensembles' => 'instruments',
            '/courriers' => 'courriers', '/activites' => 'activites', '/transactions' => 'comptabilite', '/budgets' => 'comptabilite', '/cotisations' => 'comptabilite', '/dons' => 'comptabilite', '/documents' => 'members',
            '/utilisateurs' => 'ADMIN', '/parametres/chorale' => 'ADMIN', '/profile' => null];
        $rows = [['role', 'route', 'expected', 'actual']];
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            foreach ($screens as $path => $module) {
                $expected = ($module === null || ($module === 'ADMIN' ? $role === UserRole::Admin : $role->canAccessModule($module))) ? 200 : 403;
                $actual = $this->actingAs($user)->get($path)->status();
                $rows[] = [$role->value, $path, $expected, $actual];
                $this->assertSame($expected, $actual, $role->value.' '.$path);
            }
        }

    }

    public function test_all_roles_write_create_action_matrix(): void
    {
        $components = [\App\Livewire\Members\Index::class, \App\Livewire\Works\Index::class, \App\Livewire\Rehearsals\Index::class, \App\Livewire\Performances\Index::class, \App\Livewire\Seasons\Index::class, \App\Livewire\Instruments\Index::class, Movements::class, Maintenances::class, Ensembles::class, \App\Livewire\Correspondences\Index::class, \App\Livewire\Activities\Index::class, \App\Livewire\Transactions\Index::class, \App\Livewire\Budgets\Index::class, Index::class, \App\Livewire\Donations\Index::class];
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();
            foreach ($components as $class) {
                if (! $role->canAccessModule($class::MODULE_KEY)) {
                    continue;
                }
                $test = Livewire::actingAs($user)->test($class)->call('create');
                if ($role->canManageModule($class::MODULE_KEY)) {
                    $test->assertSet('showForm', true);
                } else {
                    $test->assertForbidden();
                }
            }
        }
    }

    public function test_validated_expense_updates_budget_consumption(): void
    {
        $budget = Budget::factory()->create(['allocated_amount' => 10000, 'committed_amount' => 0, 'realized_amount' => 0]);
        $tx = Transaction::factory()->create(['budget_id' => $budget->id, 'type' => 'depense', 'status' => 'brouillon', 'amount' => 1000]);
        app(TransactionWriter::class)->transition($tx->id, TransactionStatus::Validee);
        $this->assertSame(9000.0, $budget->refresh()->available());
        $this->assertSame(10.0, $budget->consumptionRate());
    }

    public function test_old_pending_reminder_is_flagged_for_reconciliation(): void
    {
        Mail::fake();
        $member = Member::factory()->active()->create(['email' => 'audit@example.invalid', 'notify_email' => true, 'notify_sms' => false]);
        $rehearsal = Rehearsal::factory()->create();
        Reminder::create(['member_id' => $member->id, 'rehearsal_id' => $rehearsal->id, 'channel' => 'mail', 'recipient' => $member->email, 'subject' => 'Audit', 'body' => 'Audit', 'status' => 'en_attente', 'created_at' => now()->subDays(7)]);
        Reminder::query()->update(['created_at' => now()->subDays(7)]);
        $result = app(ReminderDispatcher::class)->dispatchForRehearsal($rehearsal);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame('incertain', Reminder::first()->status->value);
        Mail::assertNothingSent();
    }

    public function test_http_livewire_update_after_role_revocation_hides_finance(): void
    {
        $user = User::factory()->role(UserRole::Tresorier)->create();
        Transaction::factory()->create(['description' => 'AUDIT HTTP FINANCE']);
        $html = $this->actingAs($user)->get('/transactions')->assertOk()->getContent();
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertSame('transactions', json_decode($snapshot, true)['memo']['name']);
        $user->update(['role' => UserRole::Choriste]);
        $this->actingAs($user->fresh())->withHeader('X-Livewire', '')->postJson(app(HandleRequests::class)->getUpdateUri(), ['components' => [['snapshot' => $snapshot, 'updates' => (object) [], 'calls' => [['path' => '', 'method' => 'resetFilters', 'params' => []]]]]])->assertForbidden()->assertDontSee('AUDIT HTTP FINANCE');
    }

    public function test_file_replacement_database_failure_keeps_old_file(): void
    {
        Storage::fake('local');
        $doc = Document::factory()->create(['commission' => Commission::Direction, 'path' => 'documents/old.txt', 'original_name' => 'old.txt', 'mime_type' => 'text/plain']);
        Storage::disk('local')->put($doc->path, 'original');
        $component = Livewire::actingAs(User::factory()->role(UserRole::Admin)->create())->test(\App\Livewire\Documents\Form::class, ['document' => $doc]);
        $component->set('file', UploadedFile::fake()->create('new.txt', 1, 'text/plain'));
        $dispatcher = clone Document::getEventDispatcher();
        Document::updating(function ($document) use ($doc) {
            if ($document->id === $doc->id) {
                throw new \RuntimeException('AUDIT DB FAILURE');
            }
        });
        try {
            $component->call('save');
            $this->fail('Expected injected DB failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('AUDIT DB FAILURE', $e->getMessage());
        } finally {
            Document::setEventDispatcher($dispatcher);
        }
        $this->assertSame('documents/old.txt', $doc->fresh()->path);
        Storage::disk('local')->assertExists('documents/old.txt');
    }
}
