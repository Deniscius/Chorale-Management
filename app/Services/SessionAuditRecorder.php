<?php

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\User;
use App\Tenancy\TenantContext;

final class SessionAuditRecorder
{
    public function record(User $user, string $action): void
    {
        if (! in_array($action, ['login', 'logout'], true)) {
            throw new \InvalidArgumentException('Unsupported session audit event.');
        }

        // Guest login has not established the tenant middleware context yet.
        // Use the tenant of the successfully authenticated account.
        app(TenantContext::class)->run((int) $user->tenant_id, static function () use ($user, $action): void {
            AuditEvent::create([
                'actor_id' => $user->id,
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->id,
                'action' => $action,
                'changes' => ['before' => [], 'after' => []],
                'created_at' => now(),
            ]);
        });
    }
}
