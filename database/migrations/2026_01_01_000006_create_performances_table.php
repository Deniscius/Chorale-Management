<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->constrained('choirs')->nullOnDelete();
            $table->string('title');
            $table->string('type');
            $table->dateTime('scheduled_at');
            $table->string('venue')->nullable();
            $table->string('audience')->nullable();
            $table->string('status')->default('planifiee');
            $table->decimal('budget', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('scheduled_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performances');
    }
};
