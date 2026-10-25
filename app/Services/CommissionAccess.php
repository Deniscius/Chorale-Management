<?php

namespace App\Services;

use App\Models\ChoirCommission;
use App\Models\Member;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Collection;

final class CommissionAccess
{
    private array $memo = [];

    private array $inactiveArchives = [];

    public function inactiveArchiveCodes(): array
    {
        $tenant = app(TenantContext::class)->requireId();

        return $this->inactiveArchives[$tenant] ??= ChoirCommission::whereNotNull('archive_commission')
            ->get(['archive_commission', 'starts_on', 'ends_on', 'archived_at'])
            ->filter(fn ($c) => $c->status !== 'active')->pluck('archive_commission')->all();
    }

    public function assignments(User $user): Collection
    {
        $tenant = app(TenantContext::class)->id();
        if (! $user->exists || (int) $tenant !== (int) $user->tenant_id) {
            return collect();
        }
        $key = $tenant.':'.$user->id;
        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }
        if ($user->member_id && ! Member::whereKey($user->member_id)->exists()) {
            return $this->memo[$key] = collect();
        }

        return $this->memo[$key] = ChoirCommission::inStatus('active')
            ->where(fn ($q) => $q->where('leader_id', $user->id)->orWhereHas('members', fn ($q) => $q->where('members.id', $user->member_id ?? 0))
                ->orWhere(fn ($q) => $q->where('module_key', 'instruments')->whereHas('instrumentAssociates', fn ($q) => $q->where('users.id', $user->id))))
            ->withExists(['instrumentAssociates as is_instrument_associate' => fn ($q) => $q->where('users.id', $user->id)])
            ->get(['id', 'tenant_id', 'module_key', 'archive_commission', 'leader_id']);
    }

    public function modules(User $user, bool $manage = false): array
    {
        return $this->assignments($user)->filter(fn ($c) => ! $manage || (int) $c->leader_id === (int) $user->id || ($c->module_key === 'instruments' && $c->is_instrument_associate))
            ->pluck('module_key')->filter()->unique()->values()->all();
    }

    public function archiveCodes(User $user): array
    {
        return $this->assignments($user)->filter(fn ($c) => (int) $c->leader_id === (int) $user->id)
            ->pluck('archive_commission')->filter()->values()->all();
    }

    public function forget(): void
    {
        $this->memo = [];
        $this->inactiveArchives = [];
    }
}
