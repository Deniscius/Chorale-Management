<?php

namespace App\Services\Platform;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class Audit
{
    public static function record(string $action, ?int $tenant = null, ?string $reason = null, array $changes = []): void
    {
        DB::table('platform_audit_events')->insert(['platform_admin_id' => Auth::guard('platform')->id(), 'tenant_id' => $tenant, 'action' => $action, 'reason' => $reason, 'changes' => json_encode($changes, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
