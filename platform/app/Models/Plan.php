<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['price' => 'decimal:2', 'months' => 'integer', 'active' => 'boolean'];

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }

    /** السعر الشهري المكافئ — لمقارنة الخطط ولحساب الدخل المتكرر */
    public function monthlyRate(): float
    {
        return (float)$this->price / max(1, (int)$this->months);
    }
}
