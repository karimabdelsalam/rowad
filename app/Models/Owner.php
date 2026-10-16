<?php

namespace App\Models;

use App\Enums\IdType;
use App\Models\Concerns\HasPersonName;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Guarded(['id'])]
class Owner extends Model implements HasMedia
{
    use HasPersonName, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'id_issue_date' => 'date',
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Lookups\Bank::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)->withPivot(['share_percent', 'expenses_percent'])->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(OwnerTransaction::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    public function receiptVouchers(): MorphMany
    {
        return $this->morphMany(ReceiptVoucher::class, 'party');
    }

    public function paymentVouchers(): MorphMany
    {
        return $this->morphMany(PaymentVoucher::class, 'party');
    }

    /** Net balance owed to the owner from unsettled ledger entries. */
    public function outstandingBalance(): float
    {
        $rows = $this->transactions()->where('is_settled', false)
            ->selectRaw('direction, SUM(amount) as total')->groupBy('direction')->pluck('total', 'direction');

        return round((float) ($rows['debit'] ?? 0) - (float) ($rows['credit'] ?? 0), 2);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
