<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_commission_associates', function (Blueprint $t) {
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->foreignId('choir_commission_id')->index();
            $t->foreignId('user_id')->index();
            $t->primary(['tenant_id', 'choir_commission_id', 'user_id'], 'instrument_associates_primary');
            $t->foreign(['tenant_id', 'choir_commission_id'], 'instrument_associates_commission_fk')->references(['tenant_id', 'id'])->on('choir_commissions')->cascadeOnDelete();
            $t->foreign(['tenant_id', 'user_id'], 'instrument_associates_user_fk')->references(['tenant_id', 'id'])->on('users')->restrictOnDelete();
            $t->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instrument_commission_associates');
    }
};
