<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_units', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tenant_id')->constrained()->restrictOnDelete();
            $t->foreignId('instrument_id')->index();
            $t->unsignedInteger('ordinal');
            $t->string('inventory_code', 50)->unique();
            $t->timestamp('retired_at')->nullable();
            $t->timestamps();
            $t->unique(['tenant_id', 'instrument_id', 'ordinal']);
            $t->foreign(['tenant_id', 'instrument_id'])->references(['tenant_id', 'id'])->on('instruments')->restrictOnDelete();
            $t->index(['tenant_id', 'retired_at', 'inventory_code']);
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Les identifiants déjà imprimés ne doivent pas être supprimés ou réattribués. Restaurer une sauvegarde cohérente si nécessaire.');
    }
};
