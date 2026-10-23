<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte rendu de répétition assisté par IA.
 *
 * Le texte généré est conservé sur la répétition afin que le responsable
 * puisse le relire, le corriger puis le diffuser. La génération est toujours
 * une proposition : rien n'est publié automatiquement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rehearsals', function (Blueprint $table) {
            $table->text('ai_report')->nullable()->after('notes');
            $table->timestamp('ai_report_generated_at')->nullable()->after('ai_report');
        });
    }

    public function down(): void
    {
        Schema::table('rehearsals', function (Blueprint $table) {
            $table->dropColumn(['ai_report', 'ai_report_generated_at']);
        });
    }
};
