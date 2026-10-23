<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->string('donation_type')->nullable()->after('donor_type');
            $table->index(['donation_type', 'donated_on']);
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropIndex(['donation_type', 'donated_on']);
            $table->dropColumn('donation_type');
        });
    }
};
