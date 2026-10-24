<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tables = ['users', 'choirs', 'members', 'liturgical_seasons', 'works', 'performances', 'performance_work', 'rehearsals', 'rehearsal_attendances', 'rehearsal_work', 'instruments', 'instrument_movements', 'instrument_maintenances', 'instrument_ensembles', 'correspondence_services', 'correspondences', 'correspondence_events', 'activities', 'activity_participants', 'accounts', 'budgets', 'transactions', 'contributions', 'donations', 'reminders', 'settings', 'documents', 'reference_sequences', 'operation_locks', 'audit_events', 'commission_teams'];

    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamp('trial_started_at');
            $t->timestamp('trial_ends_at');
            $t->timestamp('paid_until')->nullable();
            $t->boolean('paid_perpetual')->default(false);
            $t->timestamp('suspended_at')->nullable();
            $t->timestamps();
        });
        DB::table('tenants')->insert(['id' => 1, 'name' => 'Espace existant', 'slug' => 'espace-existant', 'trial_started_at' => now(), 'trial_ends_at' => now()->addHours(72), 'created_at' => now(), 'updated_at' => now()]);
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SELECT setval(pg_get_serial_sequence('tenants', 'id'), 1)");
        }

        foreach ($this->tables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->foreignId('tenant_id')->default(1)->constrained('tenants')->restrictOnDelete());
            if (Schema::hasColumn($table, 'id')) {
                Schema::table($table, fn (Blueprint $t) => $t->unique(['tenant_id', 'id'], 'tenant_'.$table.'_identity'));
            }
        }
        foreach ($this->tables as $table) {
            foreach (Schema::getIndexes($table) as $index) {
                if (! $index['unique'] || $index['primary'] || in_array('tenant_id', $index['columns'], true)
                    || ($table === 'users' && in_array($index['columns'], [['email'], ['username']], true))) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($index) {
                    $t->dropUnique($index['name']);
                    $t->unique(['tenant_id', ...$index['columns']], $index['name']);
                    $t->index($index['columns'], 'tenant_lookup_'.substr(sha1($index['name']), 0, 20));
                });
            }
        }
        foreach (['reference_sequences', 'operation_locks'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropPrimary();
                $t->primary(['tenant_id', 'key']);
            });
        }
        // Keep the original FK actions. Composite NO ACTION checks also forbid cross-client links.
        foreach ($this->tables as $table) {
            foreach (Schema::getForeignKeys($table) as $foreign) {
                if (count($foreign['columns']) !== 1 || ! in_array($foreign['foreign_table'], $this->tables, true)
                    || $foreign['foreign_columns'] !== ['id']) {
                    continue;
                }
                Schema::table($table, function (Blueprint $t) use ($table, $foreign) {
                    $t->foreign(['tenant_id', $foreign['columns'][0]], 'tenant_fk_'.substr(sha1($table.'.'.$foreign['columns'][0]), 0, 20))
                        ->references(['tenant_id', 'id'])->on($foreign['foreign_table']);
                });
            }
        }
        Schema::create('tenant_licenses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->index()->constrained('tenants')->restrictOnDelete();
            $t->string('license_hash', 64)->unique();
            $t->string('provider_id')->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Cette séparation des données ne doit pas être annulée en fusionnant les espaces. Restaurer une sauvegarde cohérente avant migration si nécessaire.');
    }
};
