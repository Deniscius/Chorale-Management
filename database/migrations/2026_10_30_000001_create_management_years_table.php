<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('management_years', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('type', 16); // civile | pastorale
            $table->string('label', 64);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('theme', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'type', 'label']);
            $table->index(['tenant_id', 'type', 'starts_on', 'ends_on'], 'management_years_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_years');
    }
};
