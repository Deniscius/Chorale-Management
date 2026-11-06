<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('contribution_exemptions', function (Blueprint $table) {
            $table->foreignId('activity_id')->nullable()->after('member_id')->constrained()->nullOnDelete();
            $table->index(['tenant_id', 'member_id', 'activity_id'], 'contribution_exemption_activity_lookup');
        });
    }
};
