<?php

namespace Tests\Feature;

use App\Enums\ContributionStatus;
use App\Enums\TransactionStatus;
use App\Enums\UserRole;
use App\Livewire\Contributions\Index as ContributionsIndex;
use App\Livewire\Transactions\Index as TransactionsIndex;
use App\Models\Contribution;
use App\Models\Member;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Vérifie les règles métier du module comptabilité.
 */
class AccountingTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_tresorier_peut_marquer_une_cotisation_payee(): void
    {
        $member = Member::factory()->create();
        $contribution = Contribution::factory()->create([
            'member_id' => $member->id,
            'due_amount' => 5000,
            'paid_amount' => 0,
            'status' => ContributionStatus::Due->value,
            'paid_on' => null,
        ]);

        $tresorier = User::factory()->role(UserRole::Tresorier)->create();

        Livewire::actingAs($tresorier)
            ->test(ContributionsIndex::class)
            ->call('markAsPaid', $contribution->id)
            ->assertHasNoErrors();

        $contribution->refresh();

        $this->assertTrue($contribution->isPaid());
        $this->assertSame(ContributionStatus::Payee, $contribution->status);
        $this->assertEquals(5000, (float) $contribution->paid_amount);
        $this->assertNotNull($contribution->paid_on);
        $this->assertSame(0.0, $contribution->balance());
    }

    public function test_la_validation_dune_ecriture_renseigne_le_valideur(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => TransactionStatus::Brouillon->value,
            'validated_by' => null,
            'validated_at' => null,
        ]);

        $tresorier = User::factory()->role(UserRole::Tresorier)->create();

        Livewire::actingAs($tresorier)
            ->test(TransactionsIndex::class)
            ->call('validateTransaction', $transaction->id)
            ->assertHasNoErrors();

        $transaction->refresh();

        $this->assertSame(TransactionStatus::Validee, $transaction->status);
        $this->assertSame($tresorier->id, $transaction->validated_by);
        $this->assertNotNull($transaction->validated_at);
    }

    public function test_les_statistiques_ignorent_les_ecritures_non_validees(): void
    {
        Transaction::factory()->recette()->create(['amount' => 100_000]);
        Transaction::factory()->depense()->create(['amount' => 40_000]);
        Transaction::factory()->create([
            'type' => 'recette',
            'amount' => 900_000,
            'status' => TransactionStatus::Brouillon->value,
        ]);

        $tresorier = User::factory()->role(UserRole::Tresorier)->create();

        $stats = Livewire::actingAs($tresorier)
            ->test(TransactionsIndex::class)
            ->instance()
            ->stats;

        $this->assertEquals(100_000, $stats['recettes']);
        $this->assertEquals(40_000, $stats['depenses']);
        $this->assertEquals(60_000, $stats['solde']);
        $this->assertSame(1, $stats['brouillons']);
    }

    public function test_un_choriste_ne_peut_pas_gerer_la_comptabilite(): void
    {
        $choriste = User::factory()->role(UserRole::Choriste)->create();

        // Le choriste n'a pas accès au module comptabilité.
        $this->actingAs($choriste)->get('/cotisations')->assertForbidden();
        $this->actingAs($choriste)->get('/transactions')->assertForbidden();

        // Ni le droit d'écriture, même hors écran.
        $this->assertFalse($choriste->can('manage-module', UserRole::MODULE_COMPTABILITE));
    }
}
