<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->constrained('choirs')->nullOnDelete();
            $table->string('title');
            $table->string('type');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->foreignId('organizer_id')->nullable()->constrained('members')->nullOnDelete();
            $table->decimal('budget_allocated', 14, 2)->default(0);
            $table->decimal('budget_spent', 14, 2)->default(0);
            $table->string('status')->default('planifiee');
            $table->text('report')->nullable();
            $table->boolean('is_public')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('starts_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
