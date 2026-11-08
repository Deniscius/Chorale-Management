<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Restore only a missing, unambiguous source link; preserve all accounting amounts and statuses.
        DB::table('transactions')->whereNull('activity_id')->whereNotNull('contribution_id')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')->from('contributions')
                    ->whereColumn('contributions.id', 'transactions.contribution_id')
                    ->whereColumn('contributions.tenant_id', 'transactions.tenant_id')
                    ->whereNotNull('contributions.activity_id');
            })->update(['activity_id' => DB::raw('(SELECT activity_id FROM contributions WHERE contributions.id = transactions.contribution_id AND contributions.tenant_id = transactions.tenant_id)')]);
    }

    public function down(): void
    {
        // Do not erase valid historical activity links when rolling back this release.
    }
};
