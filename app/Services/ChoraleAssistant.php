<?php

namespace App\Services;

use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Assistant rédactionnel de la chorale.
 *
 * Produit, à partir des données déjà saisies dans l'application, une
 * proposition de compte rendu de répétition. Le résultat est toujours
 * présenté comme un brouillon : le responsable le relit, le corrige puis
 * décide de le conserver.
 *
 * Aucune donnée financière ni coordonnée personnelle n'est transmise au
 * modèle : seuls le lieu, le programme, les effectifs et les notes de séance
 * sont utilisés.
 */
class ChoraleAssistant
{
    public function __construct(private readonly AiClient $client) {}

    /**
     * L'IA est-elle utilisable ?
     */
    public function isEnabled(): bool
    {
        return (bool) config('ai.enabled');
    }

    /**
     * Rédige une proposition de compte rendu pour une répétition.
     *
     * @throws Throwable
     */
    public function generateRehearsalReport(Rehearsal $rehearsal): string
    {
        return $this->client->complete(
            $this->systemPrompt(),
            $this->rehearsalPrompt($rehearsal),
        );
    }

    /**
     * Rédige le compte rendu et l'enregistre sur la répétition.
     */
    public function storeRehearsalReport(Rehearsal $rehearsal): Rehearsal
    {
        try {
            $report = $this->generateRehearsalReport($rehearsal);
        } catch (Throwable $exception) {
            Log::warning('Échec de la génération du compte rendu IA', [
                'rehearsal_id' => $rehearsal->getKey(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $rehearsal->forceFill([
            'ai_report' => $report,
            'ai_report_generated_at' => now(),
        ])->save();

        return $rehearsal->refresh();
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Tu es le secrétaire bénévole d'une chorale paroissiale francophone.
        Tu rédiges des comptes rendus de répétition clairs, sobres et bienveillants.

        Règles :
        - Écris en français, au présent, à la troisième personne.
        - Structure en trois courts paragraphes : déroulement de la séance,
          points travaillés, points d'attention pour la prochaine fois.
        - N'invente aucune information absente des données fournies.
        - Si une information manque, ne la mentionne pas.
        - Reste concis : 120 à 200 mots, sans titre ni liste à puces.
        PROMPT;
    }

    public function rehearsalPrompt(Rehearsal $rehearsal): string
    {
        $rehearsal->loadMissing('works');

        $works = $rehearsal->works
            ->map(fn ($work) => trim(($work->title ?? '').' '.($work->composer ? '('.$work->composer.')' : '')))
            ->filter()
            ->implode(' ; ');

        $present = RehearsalAttendance::query()
            ->where('rehearsal_id', $rehearsal->getKey())
            ->where('status', 'present')
            ->count();

        $absent = RehearsalAttendance::query()
            ->where('rehearsal_id', $rehearsal->getKey())
            ->where('status', 'absent')
            ->count();

        $lines = [
            'Date de la séance : '.$rehearsal->date?->translatedFormat('l j F Y'),
            'Type : '.($rehearsal->type?->label() ?? 'répétition'),
            'Lieu : '.($rehearsal->venue ?: 'non précisé'),
            'Horaire : '.($rehearsal->timeRange() ?: 'non précisé'),
            'Œuvres au programme : '.($works !== '' ? $works : 'aucune œuvre renseignée'),
            'Choristes présents : '.$present,
            'Choristes absents : '.$absent,
        ];

        return "Rédige le compte rendu de cette répétition.\n\n".implode("\n", $lines);
    }
}
