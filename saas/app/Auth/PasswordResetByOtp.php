<?php

namespace App\Auth;

use App\Enums\NotificationEvent;
use App\Models\OtpCode;
use App\Models\User;
use App\Services\Messaging\Notifier;
use App\Services\Messaging\SmsGateway;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

/** Legacy resetpwd(mobile) → otpReset SMS → resetbyotp: a 6-digit code sent to the account's mobile. */
final class PasswordResetByOtp
{
    public const EXPIRY_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly Notifier $notifier)
    {
    }

    public static function looksLikeMobile(string $value): bool
    {
        return (bool) preg_match('/^\+?[\d\s\-]{8,16}$/', trim($value));
    }

    public function findUser(string $mobile): ?User
    {
        $digits = preg_replace('/\D/', '', $mobile);
        if (strlen($digits) < 8) {
            return null;
        }
        $wanted = SmsGateway::normalise($mobile);

        return User::query()->where('is_active', true)->where('mobile', 'like', '%'.substr($digits, -8))
            ->get()->first(fn (User $user) => SmsGateway::normalise((string) $user->mobile) === $wanted);
    }

    public function send(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        OtpCode::where('user_id', $user->id)->where('purpose', 'password_reset')->delete();
        OtpCode::create([
            'user_id' => $user->id, 'purpose' => 'password_reset', 'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        $this->notifier->send(NotificationEvent::PasswordResetOtp, ['code' => $code],
            [['name' => $user->name, 'mobile' => $user->mobile, 'email' => null]], force: true);
    }

    public function reset(User $user, #[SensitiveParameter] string $code, #[SensitiveParameter] string $password): bool
    {
        $otp = OtpCode::where('user_id', $user->id)->where('purpose', 'password_reset')->latest('id')->first();
        if ($otp === null || $otp->expires_at->isPast() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }
        if (! Hash::check(trim($code), $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $user->forceFill(['password' => $password, 'legacy_password' => null])->setRememberToken(null);
        $user->save();
        $otp->delete();
        $user->tokens()->delete(); // sign the account out of the mobile apps too

        return true;
    }
}
