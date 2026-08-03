<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * قفل محاولات الدخول.
 *
 * القفل بالـ IP لا بالحساب: لو كان بالحساب لاستطاع غريب أن يقفل حسابك عمدًا
 * بمحاولات فاشلة باسمك.
 */
class LoginThrottle
{
    private const MAX_FAILS = 8;
    private const WINDOW_MINUTES = 10;
    private const LOCK_SECONDS = 600;

    /** الثواني المتبقية على القفل، أو null إن لم يكن مقفولًا */
    public function lockedFor(?string $ip, string $action): ?int
    {
        $row = DB::table('console_log')
            ->selectRaw('COUNT(*) AS fails, COALESCE(TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()), 0) AS ago')
            ->where('action', $action)
            ->where('ip', (string)$ip)
            ->where('created_at', '>=', now()->subMinutes(self::WINDOW_MINUTES))
            ->first();

        if (!$row || (int)$row->fails < self::MAX_FAILS) {
            return null;
        }

        return max(1, self::LOCK_SECONDS - (int)$row->ago);
    }

    public function message(int $seconds): string
    {
        return 'محاولات كثيرة فاشلة — حاول بعد ' . (int)ceil($seconds / 60) . ' دقيقة.';
    }
}
