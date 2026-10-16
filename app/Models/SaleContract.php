<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\ContractStatus;
use App\Enums\SalePaymentType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** عقد بيع */
#[Guarded(['id'])]
class SaleContract extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, LogsActivity, \App\Models\Concerns\ScopedByUnitBranch;

    protected $attributes = ['calendar' => 'gregorian'];

    protected function casts(): array
    {
        return [
            'calendar' => Calendar::class,
            'contract_date' => 'date',
            'total_amount' => 'decimal:2',
            'payment_type' => SalePaymentType::class,
            'payment_plan' => 'array',
            'status' => ContractStatus::class,
            'archived_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function clients(): MorphToMany
    {
        return $this->morphToMany(Client::class, 'contract', 'contract_clients')->withPivot('share_percent');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installments(): MorphMany
    {
        return $this->morphMany(Installment::class, 'contract');
    }

    public function ownerTransactions(): MorphMany
    {
        return $this->morphMany(OwnerTransaction::class, 'contract');
    }

    public function terms(): MorphToMany
    {
        return $this->morphToMany(ContractTerm::class, 'contract', 'contract_term_selections');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
