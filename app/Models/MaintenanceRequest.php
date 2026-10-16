<?php

namespace App\Models;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Concerns\ScopedByUnitBranch;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** طلب صيانة: من بلاغ المستأجر بالصور إلى زيارة الفني وتحميل التكلفة */
#[Guarded(['id'])]
class MaintenanceRequest extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, ScopedByUnitBranch;

    /** Who pays the bill (VoucherService charge targets, plus the office itself). */
    public const CHARGE_OWNERS = 'owners';

    public const CHARGE_CONTRACT = 'contract';

    public const CHARGE_OFFICE = 'office';

    protected $attributes = ['status' => 'new', 'priority' => 'normal', 'category' => 'other'];

    protected function casts(): array
    {
        return [
            'status' => MaintenanceStatus::class,
            'category' => MaintenanceCategory::class,
            'priority' => MaintenancePriority::class,
            'scheduled_at' => 'datetime',
            'owner_decided_at' => 'datetime',
            'completed_at' => 'datetime',
            'estimated_cost' => 'decimal:2',
            'cost' => 'decimal:2',
            'rating' => 'integer',
        ];
    }

    /** @return array<string, string> */
    public static function chargeOptions(): array
    {
        return [
            self::CHARGE_OWNERS => __('على المالك (حسب نسبة المصروفات)'),
            self::CHARGE_CONTRACT => __('على المستأجر (يُضاف لأقساط عقده)'),
            self::CHARGE_OFFICE => __('على المكتب'),
        ];
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', MaintenanceStatus::OPEN);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function rentContract(): BelongsTo
    {
        return $this->belongsTo(RentContract::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function ownerDecider(): BelongsTo
    {
        return $this->belongsTo(Owner::class, 'owner_decided_by');
    }

    public function paymentVoucher(): BelongsTo
    {
        return $this->belongsTo(PaymentVoucher::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');        // the problem, from the tenant or staff
        $this->addMediaCollection('after');         // the finished work
    }

    public function registerMediaConversions(?\Spatie\MediaLibrary\MediaCollections\Models\Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(\Spatie\Image\Enums\Fit::Crop, 640, 480)->nonQueued();
    }

    /** @return list<string> photo URLs served through the office domain */
    public function photoUrls(string $collection = 'photos'): array
    {
        return $this->getMedia($collection)->map(fn ($media) => route('site.media', ['media' => $media, 'size' => 'thumb']))->values()->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'technician_name', 'scheduled_at', 'estimated_cost', 'owner_decision', 'cost', 'charge_to'])->logOnlyDirty();
    }
}
