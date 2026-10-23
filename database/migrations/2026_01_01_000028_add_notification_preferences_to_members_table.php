<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Préférences de notification des choristes.
 *
 * Chaque choriste choisit les canaux par lesquels il souhaite recevoir les
 * rappels de répétition. Les deux canaux sont désactivables indépendamment ;
 * un canal sans coordonnée correspondante est ignoré à l'envoi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->boolean('notify_email')->default(true)->after('email');
            $table->boolean('notify_sms')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notify_sms']);
        });
    }
};
