<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('voice', 30);
            $table->string('title', 180);
            $table->text('lyrics')->nullable();
            $table->string('audio_path', 512)->nullable();
            $table->string('audio_original_name')->nullable();
            $table->string('audio_mime', 100)->nullable();
            $table->unsignedBigInteger('audio_size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'voice', 'created_at']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_resources');
    }
};
