<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contribution_counter_resets', function (Blueprint $table): void {
            $table->index('member_id', 'contribution_counter_resets_member_id_index');
            $table->index('approved_by', 'contribution_counter_resets_approved_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('contribution_counter_resets', function (Blueprint $table): void {
            $table->dropIndex('contribution_counter_resets_member_id_index');
            $table->dropIndex('contribution_counter_resets_approved_by_index');
        });
    }
};
