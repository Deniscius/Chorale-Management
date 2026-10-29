<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->uuid('church_project_chorale_id')->nullable()->unique();
            $table->uuid('church_project_paroisse_id')->nullable()->index();
            $table->uuid('church_project_doyenne_id')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['church_project_chorale_id', 'church_project_paroisse_id', 'church_project_doyenne_id']);
        });
    }
};
