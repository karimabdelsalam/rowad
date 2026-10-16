<?php

namespace App\Filament\Platform\Pages;

use App\Console\Commands\Doctor;
use App\Filament\Platform\Nav;
use App\Models\Tenant;
use App\Services\Platform\HealthCheck;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

/** `rowad:doctor` in the browser: every production check with what to do, plus live queue and cron figures. */
class SystemHealthPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = Nav::Platform;

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'health';

    protected string $view = 'filament.platform.system-health';

    private const CACHE = 'platform-health-rows';

    public static function getNavigationLabel(): string
    {
        return __('صحة النظام');
    }

    public function getTitle(): string
    {
        return __('صحة النظام');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $rows = Cache::get(self::CACHE);
        $failed = is_array($rows) ? collect($rows)->where(0, '✗')->count() : 0;

        return $failed ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    protected function getViewData(): array
    {
        // The per-office migration check opens every office database; it runs only on "re-check".
        $rows = Cache::remember(self::CACHE, now()->addMinutes(10), fn () => app(HealthCheck::class)->run(array_values(array_diff(HealthCheck::SECTIONS, ['offices']))));
        $heartbeat = is_file($file = storage_path(Doctor::HEARTBEAT)) ? now()->diffInSeconds(\Carbon\Carbon::createFromTimestamp(filemtime($file))) : null;

        return [
            'rows' => $rows,
            'failed' => collect($rows)->where(0, '✗')->count(),
            'warned' => collect($rows)->where(0, '!')->count(),
            'facts' => [
                ['label' => __('آخر تشغيل للمجدول'), 'value' => $heartbeat === null ? __('لم يعمل بعد') : __('قبل :s ثانية', ['s' => (int) $heartbeat]), 'tone' => $heartbeat === null || $heartbeat > 180 ? 'danger' : 'success'],
                ['label' => __('مهام بالانتظار'), 'value' => Schema::hasTable('jobs') ? number_format(DB::table('jobs')->count()) : '-', 'tone' => null],
                ['label' => __('مهام فشلت (24 ساعة)'), 'value' => Schema::hasTable('failed_jobs') ? number_format($n = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count()) : '-', 'tone' => isset($n) && $n > 0 ? 'warning' : 'success'],
                ['label' => __('المؤسسات'), 'value' => number_format(Tenant::count()), 'tone' => null],
                ['label' => __('المساحة الحرة'), 'value' => ($free = @disk_free_space(storage_path())) ? round($free / 1073741824, 1).' GB' : '-', 'tone' => $free && $free < 2 * 1073741824 ? 'warning' : null],
                ['label' => 'PHP / Laravel', 'value' => PHP_VERSION.' / '.app()->version(), 'tone' => null],
            ],
            'checkedAt' => Cache::get(self::CACHE.':at'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recheck')->label(__('إعادة الفحص الكامل'))->icon(Heroicon::OutlinedArrowPath)
                ->action(function () {
                    Cache::put(self::CACHE, app(HealthCheck::class)->run(), now()->addMinutes(10));
                    Cache::put(self::CACHE.':at', now()->toDateTimeString(), now()->addMinutes(10));
                }),
        ];
    }
}
