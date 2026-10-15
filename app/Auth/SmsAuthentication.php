<?php

namespace App\Auth;

use App\Models\User;
use App\Notifications\SmsLoginCode;
use Filament\Auth\MultiFactor\Email\EmailAuthentication;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Legacy smslogin / smslogin_clients: after the password, a one-time code is
 * sent by SMS. Reuses Filament's hashed, expiring, throttled code handling.
 */
class SmsAuthentication extends EmailAuthentication
{
    protected int $codeExpiryMinutes = 5;

    protected string $codeNotification = SmsLoginCode::class;

    public function getId(): string
    {
        return 'sms_code';
    }

    public function getLoginFormLabel(): string
    {
        return __('رمز التحقق عبر الرسائل النصية');
    }

    public function isEnabled(Authenticatable $user): bool
    {
        return $user instanceof User && $user->smsLoginRequired();
    }

    /** Managed by the office settings and the per-user switch, not from the profile page. */
    public function getManagementSchemaComponents(): array
    {
        return [];
    }
}
