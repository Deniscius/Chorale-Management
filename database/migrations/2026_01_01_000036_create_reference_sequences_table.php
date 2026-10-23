<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('last_value')->default(0);
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('contribution_id')->nullable()->index()->constrained('contributions')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', fn (Blueprint $table) => $table->dropConstrainedForeignId('contribution_id'));
        Schema::dropIfExists('reference_sequences');
    }
};
