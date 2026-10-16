<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A pay link for an installment and the gateway's answer (e-collection). */
#[Guarded(['id'])]
class OnlinePayment extends Model
{
    public const STATUS_INITIATED = 'initiated';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SUPERSEDED = 'superseded'; // the installment changed; a fresh link replaced this one

    public const STATUSES = [
        self::STATUS_INITIATED => 'بانتظار الدفع',
        self::STATUS_PAID => 'مدفوع',
        self::STATUS_FAILED => 'فشل',
        self::STATUS_SUPERSEDED => 'مستبدل',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'payload' => 'array'];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function receiptVoucher(): BelongsTo
    {
        return $this->belongsTo(ReceiptVoucher::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** The public page on the office's own domain — also right from cron jobs, where there is no request. */
    public function url(): string
    {
        $domain = tenancy()->initialized ? tenant()->domains()->value('domain') : null;
        if ($domain === null || (app()->bound('request') && request()->getHost() === $domain)) {
            return url('/pay/'.$this->token);
        }
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return $scheme.'://'.$domain.'/pay/'.$this->token;
    }
}
