<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminders', function (Blueprint $t) {
            $t->uuid('lease_token')->nullable();
            $t->timestamp('reserved_at')->nullable();
            $t->timestamp('sending_at')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->index(['status', 'reserved_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reminders', function (Blueprint $t) {
            $t->dropIndex(['status', 'reserved_at']);
            $t->dropColumn(['lease_token', 'reserved_at', 'sending_at', 'attempts']);
        });
    }
};
