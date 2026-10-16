<?php

namespace App\Models\Concerns;

use App\Support\Branches;
use Illuminate\Database\Eloquent\Builder;

/** Contracts and instalments belong to the branch of their unit (the unit's own branch scope does the filtering). */
trait ScopedByUnitBranch
{
    public static function bootScopedByUnitBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (Branches::current() !== null) {
                $builder->whereHas('unit');
            }
        });
    }
}
