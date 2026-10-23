<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehearsal_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rehearsal_id')->constrained('rehearsals')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('status')->default('present');
            $table->text('excuse_reason')->nullable();
            $table->dateTime('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['rehearsal_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rehearsal_attendances');
    }
};
