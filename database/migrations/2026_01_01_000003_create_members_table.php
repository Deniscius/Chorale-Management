<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->constrained('choirs')->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('sex')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('voice')->nullable();
            $table->string('part')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('profession')->nullable();
            $table->string('commission')->nullable();
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->string('status')->default('actif');
            $table->text('notes')->nullable();
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['choir_id', 'status']);
            $table->index('last_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
