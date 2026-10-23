<?php

namespace Tests\Feature\Analysis;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Contrôle de couverture des index.
 *
 * Les écrans de liste filtrent sur le statut, le type, la voix et les clés
 * étrangères. Sans index, la base effectue un balayage complet de la table à
 * chaque filtre : c'est la première cause de lenteur sur un volume réel.
 */
class IndexCoverageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Index indispensables, sous la forme table => liste de colonnes indexées.
     *
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function indexProvider(): array
    {
        return [
            // Utilisateurs
            'users.role' => ['users', ['role']],

            // Commission liturgique
            'rehearsals.date' => ['rehearsals', ['date']],
            'rehearsals.status' => ['rehearsals', ['status']],
            'rehearsals.type' => ['rehearsals', ['type']],
            'performances.scheduled_at' => ['performances', ['scheduled_at']],
            'performances.status' => ['performances', ['status']],
            'works.title' => ['works', ['title']],
            'works.type' => ['works', ['type']],
            'works.season_id' => ['works', ['season_id']],
            'works.difficulty' => ['works', ['difficulty']],

            // Commission des instruments
            'instruments.name' => ['instruments', ['name']],
            'instruments.category' => ['instruments', ['category']],
            'instruments.custodian_id' => ['instruments', ['custodian_id']],
            'instrument_movements.instrument_id' => ['instrument_movements', ['instrument_id']],
            'instrument_movements.type' => ['instrument_movements', ['type']],
            'instrument_maintenances.instrument_id' => ['instrument_maintenances', ['instrument_id']],
            'instrument_maintenances.type' => ['instrument_maintenances', ['type']],
            'instrument_ensembles.conductor_id' => ['instrument_ensembles', ['conductor_id']],

            // Secrétariat
            'correspondences.reference' => ['correspondences', ['reference']],
            'correspondences.correspondence_date' => ['correspondences', ['correspondence_date']],
            'correspondences.service_id' => ['correspondences', ['service_id']],
            'activities.starts_at' => ['activities', ['starts_at']],
            'activities.status' => ['activities', ['status']],

            // Comptabilité
            'transactions.reference' => ['transactions', ['reference']],
            'transactions.transaction_date' => ['transactions', ['transaction_date']],
            'transactions.member_id' => ['transactions', ['member_id']],
            'contributions.member_id' => ['contributions', ['member_id']],
            'contributions.year' => ['contributions', ['year']],
            'contributions.status' => ['contributions', ['status']],
            'donations.member_id' => ['donations', ['member_id']],
            'donations.donor_type' => ['donations', ['donor_type']],
            'donations.donation_type' => ['donations', ['donation_type']],
            'budgets.year' => ['budgets', ['year']],

            // Archives documentaires
            'documents.commission' => ['documents', ['commission']],
            'documents.category' => ['documents', ['category']],

            // Fiches choristes
            'members.last_name' => ['members', ['last_name']],
            'members.choir_id' => ['members', ['choir_id']],
            'members.status' => ['members', ['status']],
            'members.voice' => ['members', ['voice']],

            // Tables pivots
            'rehearsal_attendances.rehearsal_id' => ['rehearsal_attendances', ['rehearsal_id']],
            'rehearsal_attendances.member_id' => ['rehearsal_attendances', ['member_id']],
            'activity_participants.activity_id' => ['activity_participants', ['activity_id']],
            'activity_participants.member_id' => ['activity_participants', ['member_id']],
        ];
    }

    /**
     * Chaque colonne filtrée doit être couverte par un index.
     */
    #[DataProvider('indexProvider')]
    public function test_les_colonnes_filtrees_sont_indexees(string $table, array $colonnes): void
    {
        $this->assertTrue(
            Schema::hasTable($table),
            "La table {$table} n'existe pas."
        );

        $indexes = Schema::getIndexes($table);

        $couvert = false;

        foreach ($indexes as $index) {
            if ($this->indexCouvre($index['columns'], $colonnes)) {
                $couvert = true;
                break;
            }
        }

        $this->assertTrue(
            $couvert,
            sprintf(
                "Aucun index ne couvre (%s) sur la table %s.\nIndex existants : %s",
                implode(', ', $colonnes),
                $table,
                implode(', ', array_map(
                    static fn (array $index): string => '('.implode(', ', $index['columns']).')',
                    $indexes
                ))
            )
        );
    }

    /**
     * Les clés étrangères doivent être indexées pour éviter un balayage
     * lors des jointures et des suppressions en cascade.
     */
    public function test_les_cles_etrangeres_sont_indexees(): void
    {
        $manquants = [];

        foreach ($this->tables() as $table) {
            $indexes = Schema::getIndexes($table);

            foreach (Schema::getForeignKeys($table) as $cleEtrangere) {
                foreach ((array) $cleEtrangere['columns'] as $colonne) {
                    $couvert = false;

                    foreach ($indexes as $index) {
                        if ($this->indexCouvre($index['columns'], [$colonne])) {
                            $couvert = true;
                            break;
                        }
                    }

                    if (! $couvert) {
                        $manquants[] = "{$table}.{$colonne}";
                    }
                }
            }
        }

        $this->assertSame([], $manquants, implode("\n", $manquants));
    }

    /**
     * Liste des tables de l'application.
     *
     * @return list<string>
     */
    protected function tables(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->reject(fn (string $table): bool => str_starts_with($table, 'sqlite_')
                || in_array($table, ['migrations', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'password_reset_tokens'], true))
            ->values()
            ->all();
    }

    /**
     * Un index couvre une liste de colonnes si celles-ci en constituent
     * le préfixe : un index composite (type, is_repertoire) couvre « type ».
     *
     * @param  list<string>  $colonnesIndex
     * @param  list<string>  $colonnesRecherchees
     */
    protected function indexCouvre(array $colonnesIndex, array $colonnesRecherchees): bool
    {
        $index = array_map('strtolower', $colonnesIndex);
        $recherches = array_map('strtolower', $colonnesRecherchees);

        if (count($recherches) > count($index)) {
            return false;
        }

        return array_slice($index, 0, count($recherches)) === $recherches;
    }
}
