<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\OfferType;
use App\Enums\PaymentCycle;
use App\Enums\RentPeriodUnit;
use App\Enums\UnitStatus;
use App\Models\Lookups\ClientCategory;
use App\Models\Lookups\DeedType;
use App\Models\Lookups\PropertyCategory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** الوحدة القابلة للتأجير أو البيع (legacy building) */
#[Guarded(['id'])]
class Unit extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia, LogsActivity;

    protected function casts(): array
    {
        return [
            'offer_type' => OfferType::class,
            'status' => UnitStatus::class,
            'is_active' => 'boolean',
            'is_furnished' => 'boolean',
            'is_published' => 'boolean',
            'show_price' => 'boolean',
            'is_featured' => 'boolean',
            'area' => 'decimal:2',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'borders' => 'array',
            'rent_amount' => 'decimal:2',
            'daily_rent' => 'decimal:2',
            'monthly_rent' => 'decimal:2',
            'rent_period' => RentPeriodUnit::class,
            'rent_payment_cycle' => PaymentCycle::class,
            'rent_commission' => 'decimal:2',
            'sale_commission' => 'decimal:2',
            'vat_applicable' => 'boolean',
            'management_fee' => 'decimal:2',
            'management_fee_cycle' => PaymentCycle::class,
            'management_fee_start_date' => 'date',
            'management_fee_next_date' => 'date',
            'calendar' => Calendar::class,
        ];
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PropertyCategory::class, 'property_category_id');
    }

    public function clientCategory(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class);
    }

    public function deedType(): BelongsTo
    {
        return $this->belongsTo(DeedType::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class)->withPivot(['share_percent', 'expenses_percent'])->withTimestamps();
    }

    public function meters(): HasMany
    {
        return $this->hasMany(UnitMeter::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(UnitReport::class);
    }

    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    public function rentContracts(): HasMany
    {
        return $this->hasMany(RentContract::class);
    }

    public function saleContracts(): HasMany
    {
        return $this->hasMany(SaleContract::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function ownerTransactions(): HasMany
    {
        return $this->hasMany(OwnerTransaction::class);
    }

    public function currentContract(): MorphTo
    {
        return $this->morphTo();
    }

    /** Units listed on the office's public landing page. */
    public function scopeListed(Builder $query): Builder
    {
        return $query->available()->where('is_published', true)->where('offer_type', OfferType::Rent);
    }

    /** Title/details in the visitor's language, falling back to Arabic. */
    public function localized(string $attribute): ?string
    {
        if (app()->getLocale() !== 'ar' && filled($this->{$attribute.'_en'})) {
            return $this->{$attribute.'_en'};
        }

        return $this->{$attribute};
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', UnitStatus::Available)->where('is_active', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
        $this->addMediaCollection('attachments');
    }

    /** Cover photo URL served through the office domain (tenant storage). */
    public function coverPhotoUrl(): ?string
    {
        $media = $this->getMedia('photos')->first();

        return $media ? route('site.media', $media) : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
