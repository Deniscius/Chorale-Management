<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehearsal_work', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rehearsal_id')->constrained('rehearsals')->cascadeOnDelete();
            $table->foreignId('work_id')->constrained('works')->cascadeOnDelete();
            $table->unsignedSmallInteger('order')->default(1);
            $table->string('key_signature')->nullable();
            $table->unsignedSmallInteger('tempo')->nullable();
            $table->string('focus')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rehearsal_id', 'work_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehearsal_work');
    }
};
