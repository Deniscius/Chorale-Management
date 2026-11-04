<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('choir_master_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('choir_commission_id')->constrained('choir_commissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'choir_commission_id', 'user_id'], 'choir_master_unique_member');
            $table->index(['tenant_id', 'user_id'], 'choir_master_user_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('choir_master_assignments');
    }
};
