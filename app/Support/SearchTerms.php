<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class SearchTerms
{
    /**
     * Search every word across approved text columns.
     * At most five words are accepted to keep SQL predicates bounded.
     *
     * @param list<string> $columns
     */
    public static function apply(Builder $query, string $search, array $columns): Builder
    {
        $words = preg_split('/\\s+/u', trim($search)) ?: [];
        foreach (array_slice($words, 0, 5) as $word) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $word).'%';
            $query->where(function (Builder $nested) use ($columns, $like): void {
                foreach ($columns as $column) {
                    $nested->orWhereLike($column, $like);
                }
            });
        }

        return $query;
    }
}
