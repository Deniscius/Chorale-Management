<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('choir_id')->nullable()->index()->constrained('choirs')->nullOnDelete();
            $table->string('commission');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('member_id')->nullable()->index()->constrained('members')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['commission', 'created_at']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
