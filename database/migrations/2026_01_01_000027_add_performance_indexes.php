<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance.
 *
 * Les écrans de liste filtrent et trient systématiquement sur le statut,
 * le type, la voix et les clés étrangères. Ces colonnes n'étaient pas
 * indexées, ce qui provoquait un balayage complet de la table dès que le
 * volume de données augmentait (comptabilité, répétition, participations).
 */
return new class extends Migration
{
    /**
     * Index à créer : table => liste de colonnes.
     *
     * @var array<string, list<string>>
     */
    protected array $indexes = [
        // Comptes utilisateurs : le module Utilisateurs filtre par rôle.
        'users' => ['role', 'member_id'],

        // Commission liturgique : filtrage par statut et type.
        'rehearsals' => ['status', 'type', 'choir_id', 'created_by'],
        'performances' => ['status', 'choir_id', 'created_by'],
        'works' => ['season_id', 'difficulty', 'choir_id'],

        // Commission des instruments : relations et type de mouvement.
        'instruments' => ['custodian_id', 'choir_id'],
        'instrument_movements' => ['responsible_id', 'created_by'],
        'instrument_maintenances' => ['type', 'created_by'],
        'instrument_ensembles' => ['conductor_id', 'choir_id'],

        // Secrétariat : filtres sur le service destinataire.
        'correspondences' => ['service_id', 'created_by'],
        'correspondence_services' => ['responsible_id'],
        'correspondence_events' => ['user_id'],
        'activities' => ['status', 'organizer_id', 'created_by', 'choir_id'],

        // Comptabilité : statut des cotisations et rattachements.
        'contributions' => ['status', 'choir_id'],
        'transactions' => ['member_id', 'budget_id', 'account_id', 'created_by', 'validated_by', 'activity_id', 'choir_id'],
        'donations' => ['member_id', 'transaction_id', 'choir_id'],
        'accounts' => ['parent_id'],
        'budgets' => ['choir_id'],

        // Fiches choristes : filtre par voix et par statut.
        'members' => ['voice', 'status'],

        // Tables pivots : consultation par membre.
        'rehearsal_attendances' => ['member_id', 'status'],
        'activity_participants' => ['member_id', 'status'],
        'rehearsal_work' => ['work_id'],
        'performance_work' => ['work_id'],

        // Chorales.
        'choirs' => ['director_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $colonnes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($colonnes as $colonne) {
                if (! Schema::hasColumn($table, $colonne)) {
                    continue;
                }

                $nom = $table.'_'.$colonne.'_index';

                if (Schema::hasIndex($table, $nom)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $table) use ($colonne, $nom) {
                    $table->index($colonne, $nom);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $colonnes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($colonnes as $colonne) {
                $nom = $table.'_'.$colonne.'_index';

                if (! Schema::hasIndex($table, $nom)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $table) use ($nom) {
                    $table->dropIndex($nom);
                });
            }
        }
    }
};
