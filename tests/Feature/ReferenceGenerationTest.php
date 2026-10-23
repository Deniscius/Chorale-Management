<?php

namespace Tests\Feature;

use App\Enums\CorrespondenceType;
use App\Models\Correspondence;
use App\Models\Transaction;
use App\Services\CorrespondenceReferenceGenerator;
use App\Services\TransactionReferenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie l'attribution automatique des chronos (courriers et écritures).
 */
class ReferenceGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_les_courriers_entrants_recoivent_un_chrono_ce(): void
    {
        $premier = Correspondence::factory()->entrant()->create([
            'correspondence_date' => '2026-03-05',
        ]);

        $second = Correspondence::factory()->entrant()->create([
            'correspondence_date' => '2026-04-11',
        ]);

        $this->assertSame('CE/2026/0001', $premier->reference);
        $this->assertSame('CE/2026/0002', $second->reference);
    }

    public function test_les_courriers_sortants_recoivent_un_chrono_cs_independant(): void
    {
        Correspondence::factory()->entrant()->create(['correspondence_date' => '2026-01-10']);

        $sortant = Correspondence::factory()->sortant()->create([
            'correspondence_date' => '2026-01-12',
        ]);

        $this->assertSame('CS/2026/0001', $sortant->reference);
    }

    public function test_chaque_annee_reinitialise_le_chrono(): void
    {
        Correspondence::factory()->entrant()->create(['correspondence_date' => '2025-12-31']);

        $nouveau = Correspondence::factory()->entrant()->create([
            'correspondence_date' => '2026-01-02',
        ]);

        $this->assertSame('CE/2026/0001', $nouveau->reference);
    }

    public function test_le_generateur_saute_la_sequence_existante(): void
    {
        $generator = app(CorrespondenceReferenceGenerator::class);

        $this->assertSame('CE/2027/0001', $generator->next(
            CorrespondenceType::Entrant,
            2027,
        ));
    }

    public function test_les_ecritures_comptables_recoivent_un_chrono_op(): void
    {
        $generator = app(TransactionReferenceGenerator::class);
        $year = now()->year;

        $this->assertSame("OP/{$year}/0001", $generator->next());

        $premiere = Transaction::factory()->recette()->create();
        $seconde = Transaction::factory()->depense()->create();

        $this->assertSame("OP/{$year}/0002", $premiere->reference);
        $this->assertSame("OP/{$year}/0003", $seconde->reference);
    }
}
