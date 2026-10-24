<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->string('password');
            $table->text('totp_secret');
            $table->bigInteger('last_totp_counter')->default(-1);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
        Schema::create('platform_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('platform_admin_id')->nullable()->constrained('platform_admins')->restrictOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->index('platform_admin_id');
            $table->index('tenant_id');
            $table->string('action', 50)->index();
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->index();
        });
        Schema::create('platform_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->index('user_id');
            $table->date('day')->index();
            $table->unsignedBigInteger('requests')->default(0);
            $table->unsignedBigInteger('errors')->default(0);
            $table->unsignedBigInteger('slow_requests')->default(0);
            $table->unsignedBigInteger('duration_ms')->default(0);
            $table->unique(['tenant_id', 'user_id', 'day']);
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_usage_daily');
        Schema::dropIfExists('platform_audit_events');
        Schema::dropIfExists('platform_admins');
    }
};
