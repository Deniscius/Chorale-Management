<?php

namespace Tests\Feature;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActivityFinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_is_period_scoped_printable_and_excludes_drafts(): void
    {
        $manager = User::factory()->role(UserRole::Directeur)->create();
        Activity::factory()->create(['title' => 'Concert de la période', 'starts_at' => '2026-05-18 18:00:00', 'budget_allocated' => 50000]);
        Activity::factory()->create(['title' => 'Hors période', 'starts_at' => '2026-08-18 18:00:00']);
        Transaction::factory()->create(['transaction_date' => '2026-05-18', 'type' => TransactionType::Recette, 'status' => TransactionStatus::Validee, 'amount' => 30000]);
        Transaction::factory()->create(['transaction_date' => '2026-05-18', 'type' => TransactionType::Depense, 'status' => TransactionStatus::Brouillon, 'amount' => 99999]);

        $this->actingAs($manager)->get(route('activities.report', ['from' => '2026-05-01', 'to' => '2026-05-31', 'orientation' => 'portrait']))
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Concert de la période')->assertDontSee('Hors période')
            ->assertSee('30 000 FCFA')->assertDontSee('99 999')
            ->assertSee('Portrait')->assertSee('Imprimer / enregistrer en PDF');
    }

    public function test_chorister_cannot_generate_a_management_report(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Choriste)->create())
            ->get(route('activities.report'))->assertForbidden();
    }
}
