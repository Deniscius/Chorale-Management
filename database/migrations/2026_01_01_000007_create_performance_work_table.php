<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_id')->constrained('performances')->cascadeOnDelete();
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('role')->nullable();
            $table->string('choir_part')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['performance_id', 'work_id']);
            $table->index(['performance_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_work');
    }
};
