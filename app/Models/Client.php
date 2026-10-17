<?php

namespace App\Models;

use App\Enums\Calendar;
use App\Enums\ClientKind;
use App\Enums\IdType;
use App\Models\Concerns\HasPersonName;
use App\Models\Lookups\ClientCategory;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/** مستأجر أو مشتري، فرد أو شركة (legacy buyer) */
#[Guarded(['id'])]
class Client extends Model implements HasMedia
{
    use HasPersonName, SoftDeletes, InteractsWithMedia, LogsActivity;

    protected $attributes = ['calendar' => 'gregorian'];

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'id_issue_date' => 'date',
            'birth_date' => 'date',
            'company_registration_date' => 'date',
            'kind' => ClientKind::class,
            'calendar' => Calendar::class,
            'billing_info' => 'array',
            'national_address' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ClientCategory::class, 'client_category_id');
    }

    public function rentContracts(): MorphToMany
    {
        return $this->morphedByMany(RentContract::class, 'contract', 'contract_clients')->withPivot('share_percent');
    }

    public function saleContracts(): MorphToMany
    {
        return $this->morphedByMany(SaleContract::class, 'contract', 'contract_clients')->withPivot('share_percent');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
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

    public function displayName(): string
    {
        return $this->kind === ClientKind::Company && $this->company_name ? $this->company_name : $this->full_name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
