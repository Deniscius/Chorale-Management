<?php

use App\Enums\Commission;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Keep every existing commission and delegation. Only correct the
        // functional module of the legacy follow-up reference commission.
        DB::table('choir_commissions')
            ->where('archive_commission', Commission::SuivieFormation->value)
            ->where('module_key', UserRole::MODULE_LITURGIQUE)
            ->update(['module_key' => UserRole::MODULE_MEMBERS]);
    }

    public function down(): void
    {
        // Do not regrant broad liturgical privileges on rollback.
    }
};
