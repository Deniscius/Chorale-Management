<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table) {
            $table->string('status', 20)->default('active');
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->string('sync_status', 20)->default('pending');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_licenses', function (Blueprint $table) {
            $table->dropIndex(['last_checked_at']);
            $table->dropColumn(['status', 'last_checked_at', 'sync_status']);
        });
    }
};
