<?php

namespace Tests\Feature\Analysis;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Choir;
use App\Models\Correspondence;
use App\Models\Instrument;
use App\Models\Rehearsal;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Work;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\InteractsWithQueryCounter;
use Tests\TestCase;

/**
 * Analyse des performances de l'application.
 *
 * Mesure le nombre de requêtes SQL déclenchées par chaque écran et
 * détecte les signatures de requêtes N+1 (un même SELECT répété dans
 * une boucle de rendu).
 *
 * Ces tests constituent un garde-fou anti-régression : si l'on ajoute une
 * requête par ligne affichée, le test échoue.
 */
class QueryBudgetTest extends TestCase
{
    use InteractsWithQueryCounter;
    use RefreshDatabase;

    /**
     * Budget maximal de requêtes SQL par écran (amorçage + rendu).
     *
     * Les valeurs correspondent aux mesures relevées après correction du
     * cache des propriétés calculées Livewire (5 à 9 requêtes par écran),
     * majorées d'une marge de sécurité. Les écrans incluent désormais une
     * requête partagée pour charger le sélecteur global des années. Un retour à l'ancienne
     * implémentation (40 à 75 requêtes) fait échouer le test.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function screenProvider(): array
    {
        return [
            'tableau de bord' => ['/dashboard', 20],
            'membres' => ['/membres', 11],
            'oeuvres' => ['/oeuvres', 11],
            'repetitions' => ['/repetitions', 11],
            'celebrations' => ['/celebrations', 10],
            'temps liturgiques' => ['/temps-liturgiques', 11],
            'instruments' => ['/instruments', 9],
            'mouvements' => ['/instruments/mouvements', 10],
            'maintenance' => ['/instruments/maintenance', 9],
            'ensembles' => ['/instruments/ensembles', 12],
            'courriers' => ['/courriers', 10],
            'activites' => ['/activites', 8],
            'transactions' => ['/transactions', 10],
            'budgets' => ['/budgets', 10],
            'cotisations' => ['/cotisations', 10],
            'dons' => ['/dons', 11],
            'archives' => ['/documents', 12],
            'utilisateurs' => ['/utilisateurs', 9],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * Chaque écran doit rester sous un budget de requêtes SQL.
     */
    #[DataProvider('screenProvider')]
    public function test_chacun_ecran_reste_sous_son_budget_de_requetes(string $path, int $budget): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->startQueryCapture();

        $this->actingAs($admin)->get($path)->assertOk();

        $queries = $this->stopQueryCapture();

        $this->assertLessThanOrEqual(
            $budget,
            $this->queryCount(),
            sprintf(
                "L'écran %s déclenche %d requêtes SQL (budget : %d).\nRépartition :\n%s",
                $path,
                $this->queryCount(),
                $budget,
                $this->formatFingerprint()
            )
        );

        $this->assertNotEmpty($queries);
    }

    /**
     * Aucun écran ne doit présenter de signature N+1 (SELECT répété > 4 fois).
     */
    #[DataProvider('screenProvider')]
    public function test_aucun_ecran_ne_present_de_signature_n_plus_1(string $path): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->startQueryCapture();

        $this->actingAs($admin)->get($path)->assertOk();

        $this->stopQueryCapture();

        $suspects = $this->detectNPlusOne(5);

        $this->assertSame(
            [],
            $suspects,
            sprintf(
                "L'écran %s présente une signature de requêtes N+1 :\n%s",
                $path,
                implode("\n", array_map(
                    static fn (string $sql, int $n): string => "  [x{$n}] {$sql}",
                    array_keys($suspects),
                    array_values($suspects)
                ))
            )
        );
    }

    /**
     * Le nombre de requêtes ne doit pas croître avec le volume de données :
     * chaque écran doit paginer ses listes.
     */
    #[DataProvider('volumeProvider')]
    public function test_le_nombre_de_requetes_ne_croit_pas_avec_le_volume(string $path): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        // Reference : jeu de données de départ.
        $this->startQueryCapture();
        $this->actingAs($admin)->get($path)->assertOk();
        $this->stopQueryCapture();
        $baseline = $this->queryCount();

        // Volume multiplié par 5 (hors membres, dont la table est déjà peuplée).
        $this->seedExtraVolume();

        $this->startQueryCapture();
        $this->actingAs($admin)->get($path)->assertOk();
        $this->stopQueryCapture();
        $charged = $this->queryCount();

        $this->assertLessThanOrEqual(
            $baseline + 2,
            $charged,
            sprintf(
                "L'écran %s passe de %d à %d requêtes après multiplication du volume : la pagination ou l'indexation est probablement absente.\n%s",
                $path,
                $baseline,
                $charged,
                $this->formatFingerprint()
            )
        );
    }

    /**
     * Écrans dont le volume de données est maîtrisé pour le test de montée en charge.
     *
     * @return array<string, array{0: string}>
     */
    public static function volumeProvider(): array
    {
        return [
            'oeuvres' => ['/oeuvres'],
            'repetitions' => ['/repetitions'],
            'instruments' => ['/instruments'],
            'courriers' => ['/courriers'],
            'activites' => ['/activites'],
            'transactions' => ['/transactions'],
            'utilisateurs' => ['/utilisateurs'],
        ];
    }

    /**
     * Produit un rapport complet des mesures, exploitable hors PHPUnit.
     */
    public function test_rapport_de_performance_de_l_application(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $report = [];

        foreach (self::screenProvider() as $label => [$path, $budget]) {
            $this->startQueryCapture();

            $this->actingAs($admin)->get($path)->assertOk();

            $queries = $this->stopQueryCapture();

            $report[$label] = [
                'chemin' => $path,
                'requetes' => $this->queryCount(),
                'budget' => $budget,
                'temps_sql_ms' => $this->queryTime(),
                'n_plus_1' => $this->detectNPlusOne(5),
                'requetes_repetees' => $this->queryFingerprint(),
            ];
        }

        $directory = storage_path('app/analysis');

        File::ensureDirectoryExists($directory);
        File::put($directory.'/queries.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->assertNotEmpty($report);
    }

    /**
     * Ajoute un volume supplémentaire de données pour le test de montée en charge.
     */
    protected function seedExtraVolume(): void
    {
        $chorale = Choir::first();

        if (! $chorale) {
            return;
        }

        Work::factory()->count(60)->create(['choir_id' => $chorale->id]);
        Rehearsal::factory()->count(40)->create(['choir_id' => $chorale->id]);
        Instrument::factory()->count(40)->create(['choir_id' => $chorale->id]);
        Correspondence::factory()->count(40)->create();
        Activity::factory()->count(40)->create(['choir_id' => $chorale->id]);
        Transaction::factory()->count(40)->create(['choir_id' => $chorale->id]);
        User::factory()->count(30)->create();
    }

    /**
     * Formate le résumé des requêtes les plus fréquentes.
     */
    protected function formatFingerprint(): string
    {
        $lines = [];

        foreach (array_slice($this->queryFingerprint(), 0, 8, true) as $sql => $count) {
            $lines[] = sprintf('  [x%d] %s', $count, mb_substr($sql, 0, 160));
        }

        return implode("\n", $lines);
    }
}
