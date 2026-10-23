<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->constrained('choirs')->nullOnDelete();
            $table->foreignId('season_id')->nullable()->constrained('liturgical_seasons')->nullOnDelete();
            $table->string('title');
            $table->string('type');
            $table->string('composer')->nullable();
            $table->string('arranger')->nullable();
            $table->string('text_author')->nullable();
            $table->string('language')->nullable();
            $table->string('key_signature')->nullable();
            $table->string('mode')->nullable();
            $table->unsignedSmallInteger('tempo')->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedTinyInteger('difficulty')->default(3);
            $table->unsignedTinyInteger('min_voices')->nullable();
            $table->json('required_voices')->nullable();
            $table->json('instrumentation')->nullable();
            $table->boolean('is_repertoire')->default(true);
            $table->string('pdf_path')->nullable();
            $table->string('midi_path')->nullable();
            $table->string('lyrics_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_repertoire']);
            $table->index('title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};
