<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\IdType;
use App\Models\Concerns\HasPersonName;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Guarded(['id'])]
class Employee extends Model implements HasMedia
{
    use HasPersonName, SoftDeletes, InteractsWithMedia;

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'id_issue_date' => 'date',
            'id_expiry_date' => 'date',
            'start_date' => 'date',
            'salary' => 'decimal:2',
            'rent_commission' => 'decimal:2',
            'sale_commission' => 'decimal:2',
            'calendar' => Calendar::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(EmployeePenalty::class);
    }

    public function receiptVouchers(): MorphMany
    {
        return $this->morphMany(ReceiptVoucher::class, 'party');
    }

    public function paymentVouchers(): MorphMany
    {
        return $this->morphMany(PaymentVoucher::class, 'party');
    }
}
