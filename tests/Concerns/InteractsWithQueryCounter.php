<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Instrument d'analyse des performances.
 *
 * Permet de mesurer le nombre de requêtes SQL et le temps passé en base
 * pour un écran donné, puis de détecter les signatures classiques de
 * requêtes N+1 (répétition du même motif SQL dans la boucle).
 */
trait InteractsWithQueryCounter
{
    /** @var array<int, array{sql: string, bindings: array, time: float}> */
    protected array $capturedQueries = [];

    /**
     * Active la capture des requêtes SQL.
     */
    protected function startQueryCapture(): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->capturedQueries = [];
    }

    /**
     * Arrête la capture et renvoie le journal des requêtes.
     *
     * @return array<int, array{sql: string, bindings: array, time: float}>
     */
    protected function stopQueryCapture(): array
    {
        $log = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->capturedQueries = array_map(static fn (array $entry): array => [
            'sql' => $entry['query'],
            'bindings' => $entry['bindings'],
            'time' => (float) $entry['time'],
        ], $log);

        return $this->capturedQueries;
    }

    /**
     * Nombre total de requêtes capturées.
     */
    protected function queryCount(): int
    {
        return count($this->capturedQueries);
    }

    /**
     * Temps cumulé passé en base (en millisecondes).
     */
    protected function queryTime(): float
    {
        return round(array_sum(array_column($this->capturedQueries, 'time')), 2);
    }

    /**
     * Requêtes les plus fréquentes, triées par nombre d'occurrences.
     *
     * @return array<string, int> SQL normalisé => nombre d'exécutions
     */
    protected function queryFingerprint(): array
    {
        $fingerprint = [];

        foreach ($this->capturedQueries as $query) {
            $sql = $this->normalizeSql($query['sql']);
            $fingerprint[$sql] = ($fingerprint[$sql] ?? 0) + 1;
        }

        arsort($fingerprint);

        return $fingerprint;
    }

    /**
     * Détecte les signatures N+1 : un même motif de SELECT répété
     * au-delà du seuil, hors requêtes d'agrégat (count/sum) legitimately
     * répétées par les statistiques.
     *
     * @return array<string, int> SQL normalisé => nombre d'exécutions
     */
    protected function detectNPlusOne(int $threshold = 5): array
    {
        $suspects = [];

        foreach ($this->queryFingerprint() as $sql => $occurrences) {
            if ($occurrences < $threshold) {
                continue;
            }

            // Les agrégats sont volontairement répétés par les cartes de statistiques.
            if (preg_match('/count\(|sum\(|avg\(|group by/i', $sql) === 1) {
                continue;
            }

            $suspects[$sql] = $occurrences;
        }

        return $suspects;
    }

    /**
     * Normalise un SQL pour comparer des motifs structurellement équivalents.
     */
    protected function normalizeSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

        return mb_strtolower($sql);
    }

    /**
     * Produit un rapport lisible des mesures.
     *
     * @return array<string, mixed>
     */
    protected function performanceReport(string $label): array
    {
        return [
            'ecran' => $label,
            'requetes' => $this->queryCount(),
            'temps_sql_ms' => $this->queryTime(),
            'requetes_repetees' => $this->queryFingerprint(),
            'signatures_n_plus_1' => $this->detectNPlusOne(),
        ];
    }
}
