<?php

namespace App\Services;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

final class ReferenceSequence
{
    public function next(string $table, string $prefix, int $year): string
    {
        $key = $prefix.'/'.$year;

        return DB::transaction(function () use ($table, $key): string {
            app(TenantContext::class)->table('reference_sequences')->insertOrIgnore(['tenant_id' => app(TenantContext::class)->requireId(), 'key' => $key, 'last_value' => 0]);
            $counter = app(TenantContext::class)->table('reference_sequences')->where('key', $key)->lockForUpdate()->first();
            // Include deleted rows and explicitly assigned references; numeric order also works after 9999.
            $last = app(TenantContext::class)->table($table)->where('reference', 'like', $key.'/%')
                ->orderByRaw('LENGTH(reference) DESC')->orderByDesc('reference')->value('reference');
            $existing = $last ? (int) substr($last, strrpos($last, '/') + 1) : 0;
            $number = max((int) $counter->last_value, $existing) + 1;
            app(TenantContext::class)->table('reference_sequences')->where('key', $key)->update(['last_value' => $number]);

            return $key.'/'.sprintf('%04d', $number);
        }, 3);
    }
}
