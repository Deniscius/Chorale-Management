<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('frequency_months')->default(0)->after('default_amount');
            $table->date('starts_on')->nullable()->after('frequency_months');
            $table->unsignedTinyInteger('due_day')->nullable()->after('starts_on');
        });

        DB::table('financial_types')->where('code', 'mensuelle')->update([
            'frequency_months' => 1,
            'due_day' => 1,
        ]);

        Schema::table('contributions', function (Blueprint $table) {
            $table->date('period_starts_on')->nullable()->after('month');
            $table->date('due_on')->nullable()->after('period_starts_on');
            $table->index(['tenant_id', 'due_on', 'status'], 'contributions_due_status_index');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('performance_id')->nullable()->after('activity_id')->constrained('performances')->nullOnDelete();
            $table->foreign(['tenant_id', 'performance_id'], 'tenant_fk_transactions_performance')
                ->references(['tenant_id', 'id'])->on('performances');
            $table->index('performance_id');
            $table->index(['tenant_id', 'performance_id', 'status'], 'transactions_performance_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_performance_status_index');
            $table->dropForeign('tenant_fk_transactions_performance');
            $table->dropConstrainedForeignId('performance_id');
        });
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropIndex('contributions_due_status_index');
            $table->dropColumn(['period_starts_on', 'due_on']);
        });
        Schema::table('financial_types', fn (Blueprint $table) => $table->dropColumn(['frequency_months', 'starts_on', 'due_day']));
    }
};
