<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rehearsal_rhythms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('choir_id')->constrained('choirs')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO: 1=Monday, 7=Sunday
            $table->time('starts_at');
            $table->time('ends_at')->nullable();
            $table->string('venue', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'choir_id', 'is_active']);
            $table->index('choir_id');
            $table->unique(['tenant_id', 'choir_id', 'weekday', 'starts_at', 'effective_from'], 'rhythms_unique_slot');
        });

        Schema::table('rehearsals', function (Blueprint $table): void {
            $table->foreignId('rehearsal_rhythm_id')->nullable()->constrained('rehearsal_rhythms')->nullOnDelete();
            $table->boolean('is_special')->default(false);
            $table->index('rehearsal_rhythm_id');
            $table->index(['tenant_id', 'choir_id', 'date', 'is_special'], 'rehearsals_calendar_lookup');
        });
    }

    public function down(): void
    {
        Schema::table('rehearsals', function (Blueprint $table): void {
            $table->dropIndex('rehearsals_calendar_lookup');
            $table->dropConstrainedForeignId('rehearsal_rhythm_id');
            $table->dropColumn('is_special');
        });
        Schema::dropIfExists('rehearsal_rhythms');
    }
};
