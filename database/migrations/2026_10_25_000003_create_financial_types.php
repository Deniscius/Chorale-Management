<?php

use App\Services\FinancialCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_types', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->index()->constrained()->restrictOnDelete();
            $t->string('code', 60)->index();
            $t->string('kind', 20);
            $t->string('label', 80);
            $t->string('label_key', 160);
            $t->decimal('default_amount', 12, 2)->nullable();
            $t->string('base_type', 40)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['tenant_id', 'code']);
            $t->unique(['tenant_id', 'id']);
            $t->unique(['tenant_id', 'kind', 'label_key']);
        });
        foreach (DB::table('tenants')->pluck('id') as $id) {
            FinancialCatalog::seed((int) $id);
        }
        Schema::table('contributions', function (Blueprint $t) {
            $t->string('type_code', 60)->default('mensuelle')->index();
            $t->dropUnique('contributions_member_id_year_month_unique');
            $t->unique(['tenant_id', 'member_id', 'year', 'month', 'type_code'], 'contributions_type_period_unique');
            $t->foreign(['tenant_id', 'type_code'])->references(['tenant_id', 'code'])->on('financial_types');
        });
        Schema::table('donations', fn (Blueprint $t) => $t->string('type_code', 60)->nullable()->index());
        DB::table('donations')->update(['type_code' => DB::raw("COALESCE(donation_type, 'autre')")]);
        Schema::table('donations', fn (Blueprint $t) => $t->foreign(['tenant_id', 'type_code'])->references(['tenant_id', 'code'])->on('financial_types'));
    }

    public function down(): void
    {
        throw new RuntimeException('Restaurer une sauvegarde cohérente pour préserver les types personnalisés et leurs écritures.');
    }
};
