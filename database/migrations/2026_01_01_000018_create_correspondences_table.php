<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correspondences', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('type');
            $table->date('correspondence_date');
            $table->dateTime('received_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->string('sender_name');
            $table->string('sender_organization')->nullable();
            $table->string('recipient_name')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('correspondence_services')->nullOnDelete();
            $table->string('subject');
            $table->text('body')->nullable();
            $table->string('channel')->default('courrier');
            $table->string('confidentiality')->default('normal');
            $table->string('status')->default('recu');
            $table->foreignId('assigned_to')->nullable()->constrained('members')->nullOnDelete();
            $table->string('document_path')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index('correspondence_date');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correspondences');
    }
};
