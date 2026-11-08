<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_dispatches', function (Blueprint $table): void {
            $table->string('mail_status', 20)->default('pending');
            $table->timestamp('mail_notified_at')->nullable();
        });
        // Previous "notified" records do not prove mail delivery. Never bulk-resend them.
        DB::table('notification_dispatches')->whereNotNull('notified_at')->update(['mail_status' => 'legacy']);
    }

    public function down(): void
    {
        Schema::table('notification_dispatches', fn (Blueprint $table) => $table->dropColumn(['mail_status', 'mail_notified_at']));
    }
};
