<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = ['pdate' => 'date', 'amount' => 'decimal:2'];

    public const METHODS = [
        'cash'     => 'كاش',
        'instapay' => 'إنستا باي',
        'paymob'   => 'باي موب (أونلاين)',
    ];

    public const STATUSES = [
        'pending'   => 'بانتظار التأكيد',
        'confirmed' => 'مؤكدة',
        'failed'    => 'فاشلة',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function scopeConfirmed($q)
    {
        return $q->where('status', 'confirmed');
    }
}
