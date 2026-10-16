<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Unit;
use App\Support\Branches;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A record with its own branch_id (building, unit, voucher). Staff tied to a branch only see that
 * branch's rows, and what they create lands in their branch; otherwise a record follows its unit
 * or building, so a voucher posted by the payment webhook still lands in the right branch.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder) {
            if (($branch = Branches::current()) !== null) {
                $builder->where($builder->getModel()->qualifyColumn('branch_id'), $branch);
            }
        });

        static::creating(function ($model) {
            if ($model->branch_id !== null) {
                return;
            }
            $model->branch_id = Branches::current()
                ?? ($model->unit_id ?? null ? Unit::withoutGlobalScopes()->whereKey($model->unit_id)->value('branch_id') : null)
                ?? ($model->building_id ?? null ? \App\Models\Building::withoutGlobalScopes()->whereKey($model->building_id)->value('branch_id') : null);
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
