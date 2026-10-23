<?php

namespace Tests\Feature\Analysis;

use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Budget;
use App\Models\Choir;
use App\Models\Contribution;
use App\Models\Correspondence;
use App\Models\Donation;
use App\Models\Instrument;
use App\Models\InstrumentMaintenance;
use App\Models\InstrumentMovement;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\RehearsalAttendance;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Work;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Contrôles d'intégrité des données.
 *
 * Vérifie que le contenu de la base respecte les contraintes déclarées
 * dans les migrations et les énumérations de l'application : aucune valeur
 * hors énumération, aucun enregistrement orphelin, aucune incohérence
 * métier (montants, dates, unicité).
 */
class DataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    /**
     * Toutes les valeurs stockées dans une colonne typée par énumération
     * doivent correspondre à un cas de l'énumération.
     */
    public function test_les_valeurs_stockees_respectent_les_enumerations(): void
    {
        $modeles = [
            Member::class,
            User::class,
            Transaction::class,
            Contribution::class,
            Donation::class,
            Correspondence::class,
            Activity::class,
            ActivityParticipant::class,
            Rehearsal::class,
            RehearsalAttendance::class,
            Work::class,
            Instrument::class,
            InstrumentMovement::class,
            InstrumentMaintenance::class,
            Budget::class,
        ];

        $violations = [];

        foreach ($modeles as $classe) {
            /** @var Model $modele */
            $modele = new $classe;
            $table = $modele->getTable();

            foreach ($modele->getCasts() as $colonne => $cast) {
                if (! is_string($cast) || ! enum_exists($cast)) {
                    continue;
                }

                if (! Schema::hasColumn($table, $colonne)) {
                    continue;
                }

                $valeurs = DB::table($table)
                    ->whereNotNull($colonne)
                    ->distinct()
                    ->pluck($colonne)
                    ->all();

                foreach ($valeurs as $valeur) {
                    if ($cast::tryFrom($valeur) === null) {
                        $violations[] = sprintf(
                            '%s.%s contient la valeur « %s » absente de %s',
                            $table,
                            $colonne,
                            (string) $valeur,
                            class_basename($cast)
                        );
                    }
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Aucune ligne ne doit faire référence à un enregistrement supprimé.
     */
    public function test_aucun_enregistrement_orphelin(): void
    {
        $violations = [];

        $relations = [
            ['members', 'choir_id', 'choirs'],
            ['works', 'season_id', 'liturgical_seasons'],
            ['works', 'choir_id', 'choirs'],
            ['rehearsals', 'choir_id', 'choirs'],
            ['rehearsal_attendances', 'rehearsal_id', 'rehearsals'],
            ['rehearsal_attendances', 'member_id', 'members'],
            ['performances', 'choir_id', 'choirs'],
            ['instruments', 'custodian_id', 'members'],
            ['instrument_movements', 'instrument_id', 'instruments'],
            ['instrument_maintenances', 'instrument_id', 'instruments'],
            ['instrument_ensembles', 'conductor_id', 'members'],
            ['correspondences', 'service_id', 'correspondence_services'],
            ['correspondences', 'assigned_to', 'members'],
            ['correspondence_events', 'correspondence_id', 'correspondences'],
            ['activities', 'organizer_id', 'members'],
            ['activity_participants', 'activity_id', 'activities'],
            ['activity_participants', 'member_id', 'members'],
            ['transactions', 'account_id', 'accounts'],
            ['transactions', 'budget_id', 'budgets'],
            ['transactions', 'member_id', 'members'],
            ['transactions', 'activity_id', 'activities'],
            ['contributions', 'member_id', 'members'],
            ['donations', 'member_id', 'members'],
            ['donations', 'transaction_id', 'transactions'],
            ['users', 'member_id', 'members'],
        ];

        foreach ($relations as [$table, $cle, $tablePivot]) {
            $orphelins = DB::table($table)
                ->whereNotNull($cle)
                ->whereNotIn($cle, function ($query) use ($tablePivot) {
                    $query->select('id')->from($tablePivot);
                })
                ->count();

            if ($orphelins > 0) {
                $violations[] = sprintf('%s.%s : %d orphelin(s)', $table, $cle, $orphelins);
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    /**
     * Les clés d'unicité métier doivent être respectées par la base.
     */
    public function test_les_contraintes_d_unicite_sont_appliquees(): void
    {
        // Une correspondance porte un chrono unique.
        $this->expectException(QueryException::class);

        $reference = Correspondence::first()->reference;

        Correspondence::factory()->create([
            'reference' => $reference,
        ]);
    }

    /**
     * Deux cotisations ne peuvent pas concerner le même membre sur le même mois.
     */
    public function test_une_cotisation_est_unique_par_membre_et_par_mois(): void
    {
        $contribution = Contribution::first();

        $this->expectException(QueryException::class);

        Contribution::factory()->create([
            'member_id' => $contribution->member_id,
            'year' => $contribution->year,
            'month' => $contribution->month,
        ]);
    }

    /**
     * Un budget est unique par chorale, exercice et catégorie.
     */
    public function test_un_budget_est_unique_par_exercice_et_categorie(): void
    {
        $budget = Budget::first();

        $this->expectException(QueryException::class);

        Budget::factory()->create([
            'choir_id' => $budget->choir_id,
            'year' => $budget->year,
            'category' => $budget->category,
        ]);
    }

    /**
     * La suppression d'une répétition supprime les présences associées.
     */
    public function test_la_suppression_dune_repetition_supprime_les_presences(): void
    {
        $repetition = Rehearsal::factory()->create();

        $membre = Member::factory()->create();

        RehearsalAttendance::create([
            'rehearsal_id' => $repetition->id,
            'member_id' => $membre->id,
            'status' => 'present',
        ]);

        $this->assertDatabaseHas('rehearsal_attendances', ['rehearsal_id' => $repetition->id]);

        $repetition->delete();

        $this->assertDatabaseMissing('rehearsal_attendances', ['rehearsal_id' => $repetition->id]);
    }

    /**
     * La suppression d'une activité supprime les participants associés.
     */
    public function test_la_suppression_dune_activite_supprime_les_participants(): void
    {
        $activite = Activity::factory()->create();

        $membre = Member::factory()->create();

        ActivityParticipant::create([
            'activity_id' => $activite->id,
            'member_id' => $membre->id,
            'role' => 'participant',
            'status' => 'present',
        ]);

        $activite->delete();

        $this->assertDatabaseMissing('activity_participants', ['activity_id' => $activite->id]);
    }

    /**
     * Les enregistrements supprimés logiquement sont exclus des listes.
     */
    public function test_les_enregistrements_supprimes_logiquement_sont_exclus_des_listes(): void
    {
        $membre = Member::factory()->create();

        $membre->delete();

        $this->assertSoftDeleted('members', ['id' => $membre->id]);

        $this->assertFalse(
            Member::query()->whereKey($membre->id)->exists(),
            'Un membre supprimé ne doit pas apparaître dans les requêtes ordinaires.'
        );
    }

    /**
     * Les montants sont strictement positifs ou nuls.
     */
    public function test_les_montants_ne_sont_pas_negatifs(): void
    {
        $this->assertSame(
            0,
            Transaction::where('amount', '<', 0)->count(),
            'Une écriture comptable ne peut pas porter de montant négatif.'
        );

        $this->assertSame(
            0,
            Contribution::where('due_amount', '<', 0)->orWhere('paid_amount', '<', 0)->count(),
            'Une cotisation ne peut pas porter de montant négatif.'
        );

        $this->assertSame(
            0,
            Donation::where('amount', '<', 0)->count(),
            'Un don ne peut pas porter de montant négatif.'
        );
    }

    /**
     * Le montant encaissé ne peut pas dépasser le montant dû.
     */
    public function test_le_montant_encaisse_ne_excede_pas_le_montant_du(): void
    {
        $violations = Contribution::query()
            ->whereColumn('paid_amount', '>', 'due_amount')
            ->count();

        $this->assertSame(
            0,
            $violations,
            'Une cotisation ne peut pas être encaissée au-delà du montant dû.'
        );
    }

    /**
     * Une répétition se termine après son début.
     */
    public function test_une_repetition_se_termine_apres_son_debut(): void
    {
        $violations = Rehearsal::query()
            ->whereNotNull('starts_at')
            ->whereNotNull('ends_at')
            ->whereColumn('ends_at', '<', 'starts_at')
            ->count();

        $this->assertSame(0, $violations, 'Une répétition doit se terminer après son début.');
    }

    /**
     * Un membre actif doit avoir une date d'adhésion.
     */
    public function test_un_membre_actif_doit_avoir_une_date_dadhésion(): void
    {
        $violations = Member::query()
            ->where('status', 'actif')
            ->whereNull('joined_on')
            ->count();

        $this->assertSame(0, $violations, 'Un choriste actif doit avoir une date d\'adhésion.');
    }

    /**
     * Un membre parti doit avoir une date de départ.
     */
    public function test_un_membre_inactif_doit_avoir_une_date_de_depart(): void
    {
        $violations = Member::query()
            ->where('status', 'inactif')
            ->whereNull('left_on')
            ->count();

        $this->assertSame(0, $violations, 'Un choriste inactif doit avoir une date de départ.');
    }

    /**
     * Chaque compte utilisateur est rattaché à au plus une fiche choriste,
     * et cette fiche existe.
     */
    public function test_chaque_compte_utilisateur_est_rattache_a_une_fiche_valide(): void
    {
        $utilisateurs = User::query()->whereNotNull('member_id')->count();

        $this->assertGreaterThan(0, $utilisateurs, 'La base de démonstration doit contenir des comptes rattachés.');

        $this->assertSame(
            0,
            User::query()
                ->whereNotNull('member_id')
                ->whereNotIn('member_id', Member::query()->select('id'))
                ->count(),
            'Un compte utilisateur ne peut pas pointer vers une fiche choriste inexistante.'
        );
    }

    /**
     * La participation à une activité est unique par membre.
     */
    public function test_un_membre_ne_participe_quune_fois_a_la_meme_activite(): void
    {
        $violations = DB::table('activity_participants')
            ->select('activity_id', 'member_id', DB::raw('count(*) as total'))
            ->groupBy('activity_id', 'member_id')
            ->havingRaw('count(*) > 1')
            ->count();

        $this->assertSame(0, $violations, 'Un membre ne peut être inscrit qu\'une fois par activité.');
    }

    /**
     * La présence à une répétition est unique par membre.
     */
    public function test_un_membre_ne_peut_etre_presents_quune_fois_a_la_meme_repetition(): void
    {
        $violations = DB::table('rehearsal_attendances')
            ->select('rehearsal_id', 'member_id', DB::raw('count(*) as total'))
            ->groupBy('rehearsal_id', 'member_id')
            ->havingRaw('count(*) > 1')
            ->count();

        $this->assertSame(0, $violations, 'Un membre ne peut avoir qu\'une seule présence par répétition.');
    }

    /**
     * Chaque chorale possède au moins un membre actif.
     */
    public function test_chaque_chorale_possede_des_membres_actifs(): void
    {
        $chorales = Choir::query()->count();

        $this->assertGreaterThan(0, $chorales);

        foreach (Choir::all() as $chorale) {
            $actifs = Member::query()
                ->where('choir_id', $chorale->id)
                ->where('status', 'actif')
                ->count();

            $this->assertGreaterThan(
                0,
                $actifs,
                "La chorale « {$chorale->name} » n'a aucun membre actif."
            );
        }
    }
}
