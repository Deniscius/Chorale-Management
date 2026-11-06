<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table): void {
            $table->boolean('participation_fee_enabled')->default(false)->after('is_public');
            $table->decimal('participation_fee_amount', 14, 2)->nullable()->after('participation_fee_enabled');
            $table->date('participation_fee_due_on')->nullable()->after('participation_fee_amount');
        });

        Schema::table('contributions', function (Blueprint $table): void {
            $table->foreignId('activity_id')->nullable()->after('member_id')->constrained('activities')->nullOnDelete();
            $table->foreign(['tenant_id', 'activity_id'], 'tenant_fk_contributions_activity')
                ->references(['tenant_id', 'id'])->on('activities');
            $table->index(['tenant_id', 'activity_id', 'member_id'], 'contributions_activity_member_index');
            $table->unique(['tenant_id', 'activity_id', 'member_id'], 'contributions_activity_member_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table): void {
            $table->dropUnique('contributions_activity_member_unique');
            $table->dropIndex('contributions_activity_member_index');
            $table->dropForeign('tenant_fk_contributions_activity');
            $table->dropConstrainedForeignId('activity_id');
        });

        Schema::table('activities', function (Blueprint $table): void {
            $table->dropColumn(['participation_fee_enabled', 'participation_fee_amount', 'participation_fee_due_on']);
        });
    }
};
