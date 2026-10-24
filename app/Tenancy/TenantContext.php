<?php

namespace App\Tenancy;

use App\Services\ChoraleIdentity;
use App\Services\SettingsStore;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TenantContext
{
    private ?int $tenantId = null;

    public function id(): ?int
    {
        return config('tenancy.enabled') ? $this->tenantId : 1;
    }

    public function requireId(): int
    {
        return $this->id() ?? throw new \LogicException('Un contexte client est obligatoire.');
    }

    public function set(?int $id): void
    {
        if ($id !== $this->tenantId) {
            $this->tenantId = $id;
            app(SettingsStore::class)->forget();
            app(ChoraleIdentity::class)->forget();
        }
    }

    public function run(int $id, Closure $callback): mixed
    {
        $previous = $this->tenantId;
        $this->set($id);
        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }

    public function key(string $key): string
    {
        return config('tenancy.enabled') ? 'tenant:'.($this->id() ?? 'guest').':'.$key : $key;
    }

    public function table(string $table): Builder
    {
        $query = DB::table($table);

        return config('tenancy.enabled') ? $query->where($table.'.tenant_id', $this->requireId()) : $query;
    }

    public function path(string $path): string
    {
        return config('tenancy.enabled') ? 'tenants/'.$this->requireId().'/'.$path : $path;
    }
}
