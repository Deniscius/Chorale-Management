<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('choir_commissions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->string('name', 120);
            $t->string('name_key', 120);
            $t->string('kind', 20);
            $t->text('mission');
            $t->date('starts_on');
            $t->date('ends_on')->nullable();
            $t->timestamp('archived_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'id']);
            $t->unique(['tenant_id', 'name_key']);
            $t->index(['tenant_id', 'archived_at', 'ends_on']);
        });
        Schema::create('choir_commission_member', function (Blueprint $t) {
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->foreignId('choir_commission_id')->index();
            $t->foreignId('member_id')->index();
            $t->primary(['tenant_id', 'choir_commission_id', 'member_id'], 'commission_membership_primary');
            $t->foreign(['tenant_id', 'choir_commission_id'], 'membership_commission_fk')->references(['tenant_id', 'id'])->on('choir_commissions')->cascadeOnDelete();
            $t->foreign(['tenant_id', 'member_id'], 'membership_member_fk')->references(['tenant_id', 'id'])->on('members')->restrictOnDelete();
            $t->index(['tenant_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('choir_commission_member');
        Schema::dropIfExists('choir_commissions');
    }
};
