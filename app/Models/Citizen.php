<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\IdType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Guarded(['id'])]
class Citizen extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $attributes = ['calendar' => 'gregorian'];

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'id_issue_date' => 'date',
            'calendar' => Calendar::class,
        ];
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
