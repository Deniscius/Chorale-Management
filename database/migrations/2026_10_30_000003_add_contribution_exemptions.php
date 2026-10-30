<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_exemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->string('type_code', 60);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index('member_id');
            $table->index('type_code');
            $table->index('approved_by');
            $table->index(['tenant_id', 'member_id', 'type_code', 'starts_on'], 'contribution_exemption_lookup');
            $table->foreign(['tenant_id', 'member_id'])->references(['tenant_id', 'id'])->on('members');
            $table->foreign(['tenant_id', 'type_code'])->references(['tenant_id', 'code'])->on('financial_types');
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->foreignId('exemption_id')->nullable()->after('due_on')->constrained('contribution_exemptions')->nullOnDelete();
            $table->index('exemption_id');
            $table->index(['tenant_id', 'exemption_id'], 'contributions_exemption_tenant_index');
            $table->foreign(['tenant_id', 'exemption_id'], 'contributions_exemption_tenant_foreign')
                ->references(['tenant_id', 'id'])->on('contribution_exemptions');
        });
    }

    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropForeign('contributions_exemption_tenant_foreign');
            $table->dropIndex('contributions_exemption_tenant_index');
            $table->dropConstrainedForeignId('exemption_id');
        });
        Schema::dropIfExists('contribution_exemptions');
    }
};
