<?php

namespace App\Models\Concerns;

/**
 * Arabic four-part name (first, father, grandfather, family) with a
 * denormalised full_name column kept in sync for search and sorting.
 */
trait HasPersonName
{
    public static function bootHasPersonName(): void
    {
        static::saving(function (self $model): void {
            $model->full_name = static::composeFullName(
                $model->first_name,
                $model->father_name,
                $model->grandfather_name,
                $model->family_name,
            );
        });
    }

    public static function composeFullName(?string ...$parts): string
    {
        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter($parts, fn ($p) => filled($p)))));
    }
}
