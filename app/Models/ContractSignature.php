<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** One party's electronic signature on a contract. */
#[Guarded(['id'])]
#[Hidden(['token_hash', 'otp_hash', 'signature'])]
class ContractSignature extends Model
{
    public const PENDING = 'pending';

    public const SIGNED = 'signed';

    public const DECLINED = 'declined';

    protected $attributes = ['status' => self::PENDING, 'otp_attempts' => 0];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'otp_attempts' => 'integer',
        ];
    }

    public function signable(): MorphTo
    {
        return $this->morphTo();
    }

    public function signer(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isSigned(): bool
    {
        return $this->status === self::SIGNED;
    }

    public function roleLabel(): string
    {
        return $this->role === 'lessor' ? __('المؤجر') : __('المستأجر');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::SIGNED => __('وقّع'),
            self::DECLINED => __('رفض التوقيع'),
            default => $this->expires_at?->isPast() ? __('انتهت صلاحية الرابط') : __('بانتظار التوقيع'),
        };
    }

    /** Masked mobile for screens the signer sees before proving who they are. */
    public function maskedMobile(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->mobile);

        // isolated left-to-right so the digits keep their order inside Arabic text
        return strlen($digits) >= 4 ? "\u{2066}".str_repeat('•', max(0, strlen($digits) - 4)).substr($digits, -4)."\u{2069}" : '';
    }
}
