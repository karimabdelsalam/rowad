<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\CpanelDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * "Is the server ready?" — one command the owner runs after installing or updating on the server.
 * Each line says what is wrong and what to do, in Arabic. Exit code 1 when something must be fixed.
 */
class Doctor extends Command
{
    protected $signature = 'rowad:doctor';

    protected $description = 'Check that the server is ready for production (Arabic report)';

    public const HEARTBEAT = 'framework/schedule-heartbeat';

    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(): int
    {
        foreach (['php', 'app', 'database', 'tenancy', 'cpanel', 'offices', 'cron', 'queue', 'mail', 'storage', 'ai', 'platform'] as $section) {
            try {
                $this->{'check'.ucfirst($section)}();
            } catch (Throwable $e) {
                $this->fail_($section, 'تعذّر الفحص: '.$e->getMessage());
            }
        }

        $this->table(['', 'الفحص', 'النتيجة'], $this->rows);
        $failed = collect($this->rows)->where(0, '✗')->count();
        $warned = collect($this->rows)->where(0, '!')->count();
        $failed
            ? $this->error("يوجد {$failed} مشكلة يجب إصلاحها قبل الإطلاق، و{$warned} تنبيه.")
            : $this->info($warned ? "جاهز للإطلاق، مع {$warned} تنبيه يُستحسن مراجعته." : 'كل شيء جاهز ✓');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function ok(string $check, string $detail): void
    {
        $this->rows[] = ['✓', $check, $detail];
    }

    private function warn_(string $check, string $detail): void
    {
        $this->rows[] = ['!', $check, $detail];
    }

    private function fail_(string $check, string $detail): void
    {
        $this->rows[] = ['✗', $check, $detail];
    }

    private function checkPhp(): void
    {
        version_compare(PHP_VERSION, '8.3.0', '>=')
            ? $this->ok('إصدار PHP', PHP_VERSION)
            : $this->fail_('إصدار PHP', PHP_VERSION.' — المطلوب 8.3 أو أحدث (MultiPHP Manager)');

        $missing = array_values(array_filter(['intl', 'gd', 'zip', 'mbstring', 'pdo_mysql', 'fileinfo', 'exif', 'curl'], fn ($e) => ! extension_loaded($e)));
        $missing
            ? $this->fail_('إضافات PHP', 'ناقص: '.implode('، ', $missing).' — فعّلها من Select PHP Version')
            : $this->ok('إضافات PHP', 'كلها موجودة');
    }

    private function checkApp(): void
    {
        blank(config('app.key')) ? $this->fail_('مفتاح التطبيق', 'نفّذ: php artisan key:generate') : $this->ok('مفتاح التطبيق', 'موجود');

        config('app.debug')
            ? $this->fail_('وضع التصحيح', 'APP_DEBUG=true يكشف تفاصيل الأخطاء للزوار — اجعلها false')
            : $this->ok('وضع التصحيح', 'مغلق');

        app()->environment('production')
            ? $this->ok('البيئة', 'production')
            : $this->warn_('البيئة', 'APP_ENV='.app()->environment().' — اجعلها production على السيرفر');

        str_starts_with((string) config('app.url'), 'https://')
            ? $this->ok('رابط الموقع', (string) config('app.url'))
            : $this->warn_('رابط الموقع', 'APP_URL='.config('app.url').' — يجب أن يبدأ بـ https://');

        app()->configurationIsCached()
            ? $this->ok('التسريع', 'الإعدادات مخزنة (optimize)')
            : $this->warn_('التسريع', 'نفّذ: php artisan optimize — بعد كل تعديل على .env');
    }

    private function checkDatabase(): void
    {
        DB::connection()->getPdo();
        $this->ok('قاعدة المنصة', DB::connection()->getDatabaseName());

        $pending = collect(File::files(database_path('migrations')))->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->diff(DB::table('migrations')->pluck('migration'))->count();
        $pending
            ? $this->fail_('تحديثات قاعدة المنصة', "{$pending} تحديث لم يُطبق — نفّذ: php artisan migrate --force")
            : $this->ok('تحديثات قاعدة المنصة', 'محدّثة');
    }

    private function checkTenancy(): void
    {
        $base = (string) config('rowad.tenancy.base_domain');
        in_array($base, ['localhost', '127.0.0.1', ''], true)
            ? $this->fail_('النطاق', 'TENANT_BASE_DOMAIN='.$base.' — اجعلها aqarnova.com')
            : $this->ok('النطاق', "المؤسسات على *.{$base}");

        in_array($base, config('tenancy.central_domains'), true)
            ? $this->ok('نطاقات المنصة', implode('، ', config('tenancy.central_domains')))
            : $this->fail_('نطاقات المنصة', "CENTRAL_DOMAINS يجب أن تتضمن {$base}");
    }

    private function checkCpanel(): void
    {
        if (config('tenancy.database.managers.mysql') !== CpanelDatabaseManager::class) {
            $this->ok('إنشاء قواعد المؤسسات', 'مباشرة عبر MySQL (ليس cPanel)');

            return;
        }
        $c = config('rowad.cpanel');
        $cli = ($c['driver'] ?? 'http') === 'cli';
        if (! $cli && (blank($c['user']) || blank($c['token']))) {
            $this->fail_('مفتاح cPanel', 'CPANEL_USER و CPANEL_API_TOKEN مطلوبان، أو CPANEL_UAPI_DRIVER=cli إن كانت الاستضافة تعطّل المفاتيح');

            return;
        }
        $prefix = (string) config('tenancy.database.prefix');
        filled($c['user']) && ! str_starts_with($prefix, $c['user'].'_')
            ? $this->fail_('بادئة قواعد المؤسسات', "TENANCY_DB_PREFIX يجب أن تبدأ بـ {$c['user']}_")
            : $this->ok('بادئة قواعد المؤسسات', $prefix);

        try {
            if ($cli) {
                $stats = CpanelDatabaseManager::cli('StatsBar', 'get_stats', ['display' => 'mysqldatabases']);
                $this->ok('اتصال cPanel', 'عبر أمر uapi المحلي');
            } else {
                $response = Http::withHeaders(['Authorization' => "cpanel {$c['user']}:{$c['token']}"])
                    ->withOptions(['verify' => (bool) $c['verify_ssl']])->timeout(20)
                    ->get("https://{$c['host']}:{$c['port']}/execute/StatsBar/get_stats", ['display' => 'mysqldatabases']);
                if (! $response->successful() || (int) $response->json('status') !== 1) {
                    $this->fail_('مفتاح cPanel', 'لم يقبل cPanel المفتاح — أنشئ مفتاحًا جديدًا من Manage API Tokens، أو استخدم CPANEL_UAPI_DRIVER=cli');

                    return;
                }
                $stats = $response->json('data');
                $this->ok('اتصال cPanel', 'المفتاح يعمل');
            }
        } catch (Throwable $e) {
            $this->fail_('اتصال cPanel', 'تعذّر تشغيل أمر uapi: '.$e->getMessage());

            return;
        }
        $stat = $stats[0] ?? [];
        $used = (int) ($stat['count'] ?? 0);
        $max = $stat['max'] ?? null;
        $unlimited = $max === null || in_array(strtolower((string) $max), ['unlimited', '-1', ''], true);
        $left = $unlimited ? null : (int) $max - $used;
        match (true) {
            $unlimited => $this->ok('حصة قواعد البيانات', "{$used} مستخدمة، غير محدودة"),
            $left <= 0 => $this->fail_('حصة قواعد البيانات', "{$used} من {$max} — لا يمكن تسجيل مؤسسات جديدة، اطلب رفع الحد"),
            $left < 10 => $this->warn_('حصة قواعد البيانات', "{$used} من {$max} — يتبقى {$left} مؤسسة فقط"),
            default => $this->ok('حصة قواعد البيانات', "{$used} من {$max}"),
        };
    }

    private function checkOffices(): void
    {
        $files = collect(File::files(database_path('migrations/tenant')))->map(fn ($f) => $f->getFilenameWithoutExtension());
        $behind = [];
        $count = 0;
        Tenant::query()->each(function (Tenant $tenant) use ($files, &$behind, &$count) {
            $count++;
            $done = $tenant->run(fn () => Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration') : collect());
            if ($files->diff($done)->isNotEmpty()) {
                $behind[] = $tenant->name;
            }
        });
        $behind
            ? $this->fail_('قواعد المؤسسات', count($behind).' مؤسسة تحتاج تحديث — نفّذ: php artisan tenants:migrate --force')
            : $this->ok('قواعد المؤسسات', "{$count} مؤسسة، كلها محدّثة");
    }

    private function checkCron(): void
    {
        $file = storage_path(self::HEARTBEAT);
        $age = is_file($file) ? time() - filemtime($file) : null;
        match (true) {
            $age === null => $this->fail_('المهام المجدولة', 'لم تعمل بعد — أضف في Cron Jobs: * * * * * cd '.base_path().' && php artisan schedule:run'
                .' (أو مجدولًا خارجيًا على /system-cron/<CRON_TOKEN> إن كانت الاستضافة تعطّل Cron Jobs)'),
            $age > 180 => $this->fail_('المهام المجدولة', 'آخر تشغيل قبل '.intdiv($age, 60).' دقيقة — تأكد أن مهمة Cron تعمل كل دقيقة'),
            default => $this->ok('المهام المجدولة', 'تعمل (آخر تشغيل قبل '.$age.' ثانية)'),
        };
    }

    private function checkQueue(): void
    {
        $connection = (string) config('queue.default');
        if ($connection === 'sync') {
            $this->warn_('طابور الرسائل', 'QUEUE_CONNECTION=sync يبطئ الصفحات عند الإرسال — استخدم database مع QUEUE_WORKER=scheduler');

            return;
        }
        if ($connection === 'database' && Schema::hasTable('jobs')) {
            $oldest = DB::table('jobs')->min('created_at');
            $waiting = $oldest ? time() - (int) $oldest : 0;
            $waiting > 600
                ? $this->fail_('طابور الرسائل', 'رسائل تنتظر منذ '.intdiv($waiting, 60).' دقيقة — العامل لا يعمل؛ تأكد من QUEUE_WORKER=scheduler والـ Cron')
                : $this->ok('طابور الرسائل', DB::table('jobs')->count().' بالانتظار');
        } else {
            $this->ok('طابور الرسائل', $connection);
        }
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count() : 0;
        $failed
            ? $this->warn_('مهام فشلت', "{$failed} خلال آخر 24 ساعة — راجع storage/logs/laravel.log")
            : $this->ok('مهام فشلت', 'لا يوجد');
    }

    private function checkMail(): void
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        match (true) {
            in_array($mailer, ['log', 'array'], true) => $this->fail_('البريد', "MAIL_MAILER={$mailer} — لن تصل أي رسالة بريد؛ اضبط SMTP"),
            blank($from) || str_ends_with($from, '@example.com') => $this->fail_('البريد', 'MAIL_FROM_ADDRESS غير مضبوط — مثلًا noreply@aqarnova.com'),
            default => $this->ok('البريد', "{$mailer} من {$from}"),
        };
    }

    private function checkStorage(): void
    {
        $bad = array_filter([storage_path(), storage_path('framework'), storage_path('logs'), base_path('bootstrap/cache')], fn ($p) => ! is_writable($p));
        $bad
            ? $this->fail_('صلاحيات الكتابة', 'لا يمكن الكتابة في: '.implode('، ', $bad))
            : $this->ok('صلاحيات الكتابة', 'storage و bootstrap/cache');

        $free = @disk_free_space(storage_path());
        if ($free !== false) {
            $gb = round($free / 1073741824, 1);
            $gb < 2 ? $this->warn_('المساحة الحرة', "{$gb} جيجا فقط") : $this->ok('المساحة الحرة', "{$gb} جيجا");
        }
    }

    private function checkAi(): void
    {
        \App\Services\Ai\ClaudeClient::configured()
            ? $this->ok('الذكاء الاصطناعي', 'المفتاح والنموذج مضبوطان ('.config('rowad.ai.model').')')
            : $this->warn_('الذكاء الاصطناعي', 'ANTHROPIC_API_KEY و AI_MODEL غير مضبوطين — المساعد سيظهر "غير مفعّل"');
    }

    private function checkPlatform(): void
    {
        User::where('is_super_admin', true)->exists()
            ? $this->ok('مدير المنصة', 'موجود')
            : $this->fail_('مدير المنصة', 'لا يوجد — اضبط PLATFORM_ADMIN_EMAIL و PLATFORM_ADMIN_PASSWORD ثم: php artisan db:seed --force');

        filled(config('rowad.platform_admin.password'))
            ? $this->warn_('كلمة مرور المدير في .env', 'احذف PLATFORM_ADMIN_PASSWORD من .env بعد أول دخول')
            : $this->ok('كلمة مرور المدير في .env', 'غير محفوظة في الملف');

        filled(config('rowad.contact.email')) || filled(config('rowad.contact.phone'))
            ? $this->ok('بيانات التواصل', 'تظهر في الصفحة الرئيسية')
            : $this->warn_('بيانات التواصل', 'PLATFORM_CONTACT_EMAIL / PHONE فارغة — لا تظهر وسيلة تواصل للزوار');

        $demo = Tenant::whereHas('domains', fn ($q) => $q->where('domain', 'demo.'.config('rowad.tenancy.base_domain')))->first();
        $demo?->isDemo()
            ? $this->ok('الحساب التجريبي', 'demo.'.config('rowad.tenancy.base_domain').(config('rowad.demo_reset_daily') ? '، يُعاد كل ليلة' : ''))
            : $this->warn_('الحساب التجريبي', 'غير موجود — نفّذ: php artisan rowad:demo');
    }
}
