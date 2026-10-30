<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** A recipient is distinct from the member responsible for processing a letter. */
    public function up(): void
    {
        Schema::table('correspondences', function (Blueprint $table) {
            $table->foreignId('recipient_member_id')->nullable()->after('recipient_name')->constrained('members')->nullOnDelete();
            $table->index('recipient_member_id', 'correspondence_recipient_member_index');
            $table->index(['tenant_id', 'recipient_member_id'], 'correspondence_recipient_tenant_index');
            $table->foreign(['tenant_id', 'recipient_member_id'], 'correspondence_recipient_tenant_foreign')
                ->references(['tenant_id', 'id'])->on('members');
        });
    }

    public function down(): void
    {
        Schema::table('correspondences', function (Blueprint $table) {
            $table->dropForeign('correspondence_recipient_tenant_foreign');
            $table->dropIndex('correspondence_recipient_tenant_index');
            $table->dropIndex('correspondence_recipient_member_index');
            $table->dropConstrainedForeignId('recipient_member_id');
        });
    }
};
