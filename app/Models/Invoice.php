<?php

namespace App\Models;

use App\Enums\Calendar;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** فاتورة (متوافقة مع ZATCA) */
#[Guarded(['id'])]
class Invoice extends Model
{
    use LogsActivity;

    protected $attributes = ['calendar' => 'gregorian', 'kind' => 'invoice', 'zatca_attempts' => 0];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'issued_at' => 'datetime',
            'zatca_messages' => 'array',
            'zatca_submitted_at' => 'datetime',
            'calendar' => Calendar::class,
            'items' => 'array',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function zatcaDevice(): BelongsTo
    {
        return $this->belongsTo(ZatcaDevice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
