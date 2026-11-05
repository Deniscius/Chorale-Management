<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('member_join_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255);
            $table->string('phone', 30)->nullable();
            $table->string('voice', 30)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('email_verification_hash', 64)->unique();
            $table->timestamp('email_verification_expires_at');
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['tenant_id', 'email_verified_at']);
            $table->index(['tenant_id', 'email']);
            $table->index('member_id');
            $table->index('reviewed_by');
            $table->foreign(['tenant_id', 'member_id'])->references(['tenant_id', 'id'])->on('members')->nullOnDelete();
            $table->foreign(['tenant_id', 'reviewed_by'])->references(['tenant_id', 'id'])->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_join_requests');
    }
};
