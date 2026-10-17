<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/** حساب في دليل الحسابات */
#[Guarded(['id'])]
class Account extends Model
{
    use LogsActivity;

    protected $attributes = ['is_group' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'is_group' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** Accounts that take journal lines (not headings), active ones. */
    public function scopePostable(Builder $query): Builder
    {
        return $query->where('is_group', false)->where('is_active', true);
    }

    public function label(): string
    {
        $name = app()->getLocale() === 'en' && $this->name_en ? $this->name_en : $this->name;

        return "{$this->code} — {$name}";
    }

    public function displayName(): string
    {
        return app()->getLocale() === 'en' && $this->name_en ? $this->name_en : $this->name;
    }

    /** System accounts (used by the automatic postings) and accounts with entries can't be removed. */
    public function isDeletable(): bool
    {
        return $this->system_key === null && $this->bank_account_id === null
            && ! $this->lines()->exists() && ! $this->children()->exists();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
