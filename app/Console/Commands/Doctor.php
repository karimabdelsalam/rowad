<?php

namespace App\Console\Commands;

use App\Services\Platform\HealthCheck;
use Illuminate\Console\Command;

/**
 * "Is the server ready?" — one command the owner runs after installing or updating on the server.
 * Each line says what is wrong and what to do, in Arabic. Exit code 1 when something must be fixed.
 * The checks themselves live in App\Services\Platform\HealthCheck (shared with the platform console).
 */
class Doctor extends Command
{
    protected $signature = 'rowad:doctor';

    protected $description = 'Check that the server is ready for production (Arabic report)';

    public const HEARTBEAT = 'framework/schedule-heartbeat';

    public function handle(): int
    {
        $rows = app(HealthCheck::class)->run();

        $this->table(['', 'الفحص', 'النتيجة'], $rows);
        $failed = collect($rows)->where(0, '✗')->count();
        $warned = collect($rows)->where(0, '!')->count();
        $failed
            ? $this->error("يوجد {$failed} مشكلة يجب إصلاحها قبل الإطلاق، و{$warned} تنبيه.")
            : $this->info($warned ? "جاهز للإطلاق، مع {$warned} تنبيه يُستحسن مراجعته." : 'كل شيء جاهز ✓');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
