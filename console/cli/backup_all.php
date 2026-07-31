<?php
/**
 * نسخة احتياطية من كل قواعد العيادات + قاعدة التحكم.
 *
 * تُشغَّل من الكرون:
 *   0 3 * * * /usr/bin/php /path/to/console/cli/backup_all.php /path/to/backups
 *
 * كل قاعدة في ملف مستقل، فاستعادة عيادة واحدة لا تمس البقية. الملفات القديمة
 * تُحذف بعد المدة المحددة حتى لا يمتلئ القرص فتتوقف النسخ صامتة.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../inc/functions.php';
$configFile = __DIR__ . '/../inc/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "لا يوجد inc/config.php\n");
    exit(1);
}
require $configFile;

$dir = rtrim($argv[1] ?? (__DIR__ . '/../../backups'), '/');
$keepDays = (int)($argv[2] ?? 14);

if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
    fwrite(STDERR, "تعذر إنشاء مجلد النسخ: $dir\n");
    exit(1);
}

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$stamp = date('Y-m-d_His');
$targets = [['قاعدة التحكم', DB_NAME]];
foreach ($pdo->query("SELECT name, db_name FROM clinics WHERE db_name <> '' ORDER BY id") as $r) {
    $targets[] = [$r['name'], $r['db_name']];
}

// كلمة المرور عبر البيئة لا سطر الأوامر، فلا تظهر في قائمة العمليات
putenv('MYSQL_PWD=' . DB_PASS);

$ok = $fail = 0;
foreach ($targets as [$label, $db]) {
    $file = sprintf('%s/%s_%s.sql.gz', $dir, $db, $stamp);
    $cmd = sprintf(
        'mysqldump --single-transaction --quick --default-character-set=utf8mb4 -h%s -u%s %s 2>/dev/null | gzip > %s',
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_USER),
        escapeshellarg($db),
        escapeshellarg($file)
    );
    exec($cmd, $out, $code);

    // نجاح mysqldump وحده لا يكفي: ملف فارغ يعني نسخة بلا محتوى
    $size = is_file($file) ? (int)filesize($file) : 0;
    if ($code === 0 && $size > 100) {
        $ok++;
        printf("✔ %-28s %s (%s)\n", $label, basename($file), fmt_bytes($size));
    } else {
        $fail++;
        fwrite(STDERR, sprintf("✖ %-28s فشل (كود %d، حجم %d)\n", $label, $code, $size));
        @unlink($file);
    }
}

// تنظيف النسخ الأقدم من المدة المحددة
$removed = 0;
if ($keepDays > 0) {
    $cutoff = time() - ($keepDays * 86400);
    foreach (glob($dir . '/*.sql.gz') ?: [] as $old) {
        if (filemtime($old) < $cutoff && @unlink($old)) {
            $removed++;
        }
    }
}

printf("\nتم: %d نجحت، %d فشلت، %d ملف قديم حُذف.\n", $ok, $fail, $removed);
exit($fail > 0 ? 1 : 0);

function fmt_bytes(int $b): string
{
    return $b > 1048576 ? round($b / 1048576, 1) . ' م.ب' : round($b / 1024, 1) . ' ك.ب';
}
