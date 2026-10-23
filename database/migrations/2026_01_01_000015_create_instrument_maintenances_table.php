<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instrument_id')->constrained('instruments')->cascadeOnDelete();
            $table->string('type');
            $table->text('description')->nullable();
            $table->string('provider')->nullable();
            $table->decimal('cost', 14, 2)->default(0);
            $table->date('performed_on')->nullable();
            $table->date('scheduled_for')->nullable();
            $table->string('status')->default('planifiee');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['instrument_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_maintenances');
    }
};
