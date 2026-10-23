<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class ReferenceSequence
{
    public function next(string $table, string $prefix, int $year): string
    {
        $key = $prefix.'/'.$year;

        return DB::transaction(function () use ($table, $key): string {
            DB::table('reference_sequences')->insertOrIgnore(['key' => $key, 'last_value' => 0]);
            $counter = DB::table('reference_sequences')->where('key', $key)->lockForUpdate()->first();
            // Include deleted rows and explicitly assigned references; numeric order also works after 9999.
            $last = DB::table($table)->where('reference', 'like', $key.'/%')
                ->orderByRaw('LENGTH(reference) DESC')->orderByDesc('reference')->value('reference');
            $existing = $last ? (int) substr($last, strrpos($last, '/') + 1) : 0;
            $number = max((int) $counter->last_value, $existing) + 1;
            DB::table('reference_sequences')->where('key', $key)->update(['last_value' => $number]);

            return $key.'/'.sprintf('%04d', $number);
        }, 3);
    }
}
