<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'due_date'   => 'date',
        'amount'     => 'decimal:2',
        'paid'       => 'decimal:2',
        'months'     => 'integer',
        'applied'    => 'boolean',
    ];

    public const STATUSES = [
        'unpaid'  => 'غير مدفوعة',
        'partial' => 'مدفوعة جزئيًا',
        'paid'    => 'مدفوعة',
        'void'    => 'ملغاة',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function remaining(): float
    {
        return round(max(0, (float)$this->amount - (float)$this->paid), 2);
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && in_array($this->status, ['unpaid', 'partial'], true);
    }
}
