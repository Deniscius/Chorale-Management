<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

final class CalendarYears
{
    public static function for(Model $model, string $column): array
    {
        $connection = $model->getConnection();
        $column = $connection->getQueryGrammar()->wrap($column);
        $expression = $connection->getDriverName() === 'sqlite'
            ? "CAST(strftime('%Y', {$column}) AS INTEGER)"
            : "EXTRACT(YEAR FROM {$column})";

        return $model->newQuery()->selectRaw("DISTINCT {$expression} AS annee")
            ->orderByDesc('annee')->pluck('annee')->filter()->map(fn ($year) => (int) $year)->values()->all();
    }
}
