<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Historique des rappels envoyés.
 *
 * Cette table sert de journal d'envoi : elle garantit l'idempotence de la
 * commande (un même rappel n'est jamais envoyé deux fois), trace les échecs
 * et permet d'auditer les communications de la chorale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rehearsal_id')->constrained('rehearsals')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();

            // Canal utilisé : « mail » ou « sms ».
            $table->string('channel');

            // Coordonnée effective (adresse e-mail ou numéro de téléphone).
            $table->string('recipient');

            $table->string('subject')->nullable();
            $table->text('body')->nullable();

            // Cycle de vie : en_attente, envoye, echec, ignore.
            $table->string('status')->default('en_attente');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();

            $table->timestamps();

            // Un même canal ne peut être notifié qu'une fois par répétition.
            $table->unique(['rehearsal_id', 'member_id', 'channel'], 'reminders_unique_destinataire');
            $table->index('member_id');
            $table->index(['status', 'sent_at']);
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
