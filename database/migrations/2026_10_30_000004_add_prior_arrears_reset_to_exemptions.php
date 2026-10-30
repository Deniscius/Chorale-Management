<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_exemptions', function (Blueprint $table) {
            $table->boolean('clears_prior_arrears')->default(false)->after('ends_on');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_exemptions', fn (Blueprint $table) => $table->dropColumn('clears_prior_arrears'));
    }
};
