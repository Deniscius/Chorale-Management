<?php

namespace App\Tenancy;

use Illuminate\Validation\DatabasePresenceVerifier;

final class TenantPresenceVerifier extends DatabasePresenceVerifier
{
    private function conditions($table, $column, array $extra): array
    {
        if (TenantContext::isolated() && in_array($table, config('tenant-tables.tables'), true)
            && ! ($table === 'users' && in_array($column, ['email', 'username'], true))) {
            $extra['tenant_id'] = (string) app(TenantContext::class)->requireId();
        }

        return $extra;
    }

    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        return parent::getCount($collection, $column, $value, $excludeId, $idColumn, $this->conditions($collection, $column, $extra));
    }

    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        return parent::getMultiCount($collection, $column, $values, $this->conditions($collection, $column, $extra));
    }
}
