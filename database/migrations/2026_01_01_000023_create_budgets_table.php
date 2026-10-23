<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->constrained('choirs')->nullOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('category');
            $table->decimal('allocated_amount', 14, 2)->default(0);
            $table->decimal('committed_amount', 14, 2)->default(0);
            $table->decimal('realized_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['choir_id', 'year', 'category']);
            $table->index('year');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
