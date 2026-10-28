<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letterhead_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $table->string('original_name');
            $table->string('original_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->string('header_path')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('header_height_mm')->default(45);
            $table->text('error_message')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'id']);
            $table->index(['tenant_id', 'status', 'activated_at']);
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letterhead_templates');
    }
};
