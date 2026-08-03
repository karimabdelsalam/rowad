<?php
/**
 * تشغيل التذكيرات على كل العيادات (وضع SaaS).
 *
 * سطر الكرون:
 *   0 18 * * *  /usr/bin/php /path/to/clinic/cron/reminders_all.php
 *
 * في الوضع المستقل يعمل على العيادة الواحدة، فيصلح سطر كرون واحد للحالتين.
 *
 * فشل عيادة لا يوقف البقية: كل عيادة في محاولة مستقلة، ويُطبع في النهاية
 * ملخص بمن نجح ومن فشل حتى لا يمر خطأ صامتًا.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/inc/tenant.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Cairo');
mb_internal_encoding('UTF-8');

/**
 * تشغيل تذكيرات عيادة واحدة.
 *
 * التضمين داخل دالة يجعل متغيّرات السكربت محلية، فلا تتسرب عدّادات عيادة
 * إلى التي تليها.
 */
function run_clinic_reminders(string $dbName): void
{
    global $pdo;
    $pdo = app_pdo($dbName);
    setting_flush();
    include __DIR__ . '/reminders.php';
}

$clinics = all_clinic_dbs();
if (!$clinics) {
    exit("لا توجد عيادات نشطة.\n");
}

$ok = $fail = 0;
foreach ($clinics as $c) {
    echo "\n=== " . $c['name'] . " (" . $c['db'] . ") ===\n";
    try {
        run_clinic_reminders($c['db']);
        $ok++;
    } catch (Throwable $ex) {
        $fail++;
        fwrite(STDERR, '  ✖ فشل: ' . $ex->getMessage() . "\n");
    }
}

printf("\n---\nاكتمل: %d عيادة نجحت، %d فشلت.\n", $ok, $fail);
exit($fail > 0 ? 1 : 0);
