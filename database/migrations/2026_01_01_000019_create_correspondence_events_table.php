<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_id')->constrained('correspondences')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('correspondence_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondence_events');
    }
};
