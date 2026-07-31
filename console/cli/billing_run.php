<?php
/**
 * دورة الفوترة اليومية.
 *
 * سطر الكرون:
 *   0 6 * * *  /usr/bin/php /path/to/console/cli/billing_run.php
 *   إضافة --dry لعرض ما سيحدث دون تنفيذه.
 *
 * ثلاث مهام بالترتيب:
 *   1) إصدار فاتورة تجديد قبل انتهاء الاشتراك بمدة محددة.
 *   2) إيقاف العيادات التي تجاوزت مهلة السماح دون سداد.
 *   3) إعادة تفعيل عيادة سدّدت وهي موقوفة.
 *
 * كل ذلك مبني على أن التمديد يحدث تلقائيًا عند اكتمال السداد
 * (invoice_recalc)، فالسكربت لا يمدّ اشتراكًا بنفسه أبدًا.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

require __DIR__ . '/../inc/functions.php';
require __DIR__ . '/../inc/whatsapp.php';
$configFile = __DIR__ . '/../inc/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "لا يوجد inc/config.php\n");
    exit(1);
}
require $configFile;
date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Cairo');
mb_internal_encoding('UTF-8');

$dry = in_array('--dry', $argv, true);

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$leadDays = max(1, (int)setting('invoice_lead_days', '7'));
$grace    = max(0, (int)setting('grace_days', '7'));
$today    = date('Y-m-d');
$dueSoon  = date('Y-m-d', strtotime("+$leadDays days"));

echo ($dry ? "[تجربة] " : '') . "دورة الفوترة — $today\n\n";

/**
 * إشعار واتساب للعميل — يُتخطى بصمت إن لم يكن مزوّد الإرسال مضبوطًا أو لا رقم.
 */
$notify = function (array $clinic, string $text, string $what) use ($pdo, $dry): void {
    if ($dry || !wa_enabled() || trim((string)($clinic['phone'] ?? '')) === '') {
        return;
    }
    $res = wa_send($clinic['phone'], $text);
    $pdo->prepare('INSERT INTO console_log (user_id, action, entity, entity_id, summary, ip)
                   VALUES (NULL, ?, ?, ?, ?, ?)')
        ->execute([
            $res['ok'] ? 'wa_sent' : 'wa_failed', 'clinic', (int)$clinic['id'],
            $what . ' — ' . $clinic['name'] . ($res['ok'] ? '' : ' (' . $res['error'] . ')'), 'cron',
        ]);
    printf("      واتساب: %s\n", $res['ok'] ? 'أُرسل ✓' : 'فشل — ' . $res['error']);
};

/* ------------------------------------------ 1) فواتير التجديد */

$rows = $pdo->prepare(
    "SELECT c.*, p.name AS plan_name, p.months, p.price
     FROM clinics c JOIN plans p ON p.id = c.plan_id
     WHERE c.status IN ('trial','active')
       AND c.expires_at IS NOT NULL
       AND c.expires_at <= ?
       AND p.price > 0
       AND NOT EXISTS (
           SELECT 1 FROM invoices i
           WHERE i.clinic_id = c.id AND i.status IN ('unpaid','partial')
       )"
);
$rows->execute([$dueSoon]);
$issued = 0;

foreach ($rows->fetchAll() as $c) {
    /*
     * شرط NOT EXISTS أعلاه يمنع تكرار الفاتورة ما دامت السابقة غير مسددة.
     * وهذا الفحص يمنع إصدار فاتورة ثانية لنفس الدورة بعد سداد الأولى مباشرة.
     */
    $dup = $pdo->prepare(
        "SELECT id FROM invoices
         WHERE clinic_id = ? AND status = 'paid' AND months = ? AND issue_date >= ?"
    );
    $dup->execute([$c['id'], (int)$c['months'], date('Y-m-d', strtotime("-{$leadDays} days"))]);
    if ($dup->fetchColumn()) {
        continue;
    }

    printf("  فاتورة تجديد: %-30s %s (ينتهي %s)\n",
        mb_substr($c['name'], 0, 30), money($c['price']), $c['expires_at']);

    if (!$dry) {
        $number = next_invoice_number($pdo);
        $payToken = bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO invoices (clinic_id, number, issue_date, due_date, amount, months,
                       plan_name, notes, pay_token) VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([
                $c['id'], $number, $today, $c['expires_at'],
                $c['price'], (int)$c['months'], $c['plan_name'],
                'تجديد تلقائي', $payToken,
            ]);
        $pdo->prepare('INSERT INTO console_log (user_id, action, entity, entity_id, summary, ip)
                       VALUES (NULL, ?, ?, ?, ?, ?)')
            ->execute(['auto_invoice', 'invoice', (int)$pdo->lastInsertId(),
                       'فاتورة تجديد تلقائية ' . $number . ' — ' . $c['name'], 'cron']);

        // رابط السداد يوصل للعميل فورًا بدل انتظارك تبعته يدويًا
        $base = rtrim(setting('console_url', ''), '/');
        $payUrl = $base !== '' ? $base . '/pay.php?t=' . $payToken : '';
        $notify($c,
            'مرحبًا ' . ($c['owner_name'] ?: $c['name']) . " 🌿\n"
            . 'اشتراكك في ' . setting('brand_name', 'النظام') . ' ينتهي في ' . fmt_date($c['expires_at']) . ".\n"
            . 'فاتورة التجديد ' . $number . ' بمبلغ ' . money($c['price'])
            . ($payUrl !== '' ? "\nللسداد: " . $payUrl : ''),
            'إرسال فاتورة التجديد ' . $number);
    }
    $issued++;
}

/* ------------------------------------------ 2) إيقاف المتأخرين */

$cutoff = date('Y-m-d', strtotime("-$grace days"));
$late = $pdo->prepare(
    "SELECT id, name, owner_name, phone, expires_at FROM clinics
     WHERE status IN ('trial','active') AND expires_at IS NOT NULL AND expires_at < ?"
);
$late->execute([$cutoff]);
$suspended = 0;

foreach ($late->fetchAll() as $c) {
    printf("  إيقاف: %-30s (انتهى %s، تجاوز مهلة %d يوم)\n",
        mb_substr($c['name'], 0, 30), $c['expires_at'], $grace);
    if (!$dry) {
        $pdo->prepare("UPDATE clinics SET status = 'suspended' WHERE id = ?")->execute([$c['id']]);
        $pdo->prepare('INSERT INTO console_log (user_id, action, entity, entity_id, summary, ip)
                       VALUES (NULL, ?, ?, ?, ?, ?)')
            ->execute(['auto_suspend', 'clinic', (int)$c['id'],
                       'إيقاف تلقائي بعد تجاوز المهلة: ' . $c['name'], 'cron']);

        // أحدث فاتورة غير مسددة ليصل رابط سدادها مع إشعار الإيقاف
        $tok = $pdo->prepare("SELECT pay_token FROM invoices
                              WHERE clinic_id = ? AND status IN ('unpaid','partial')
                              ORDER BY id DESC LIMIT 1");
        $tok->execute([$c['id']]);
        $base = rtrim(setting('console_url', ''), '/');
        $payUrl = ($t = $tok->fetchColumn()) && $base !== '' ? $base . '/pay.php?t=' . $t : '';
        $notify($c,
            'عزيزي عميل ' . setting('brand_name', 'النظام') . "،\n"
            . 'تم إيقاف اشتراك «' . $c['name'] . '» لعدم التجديد. بياناتك محفوظة بالكامل، '
            . 'ويعود كل شيء فور السداد.'
            . ($payUrl !== '' ? "\nللسداد: " . $payUrl : '')
            . (setting('support_phone') !== '' ? "\nللتواصل: " . setting('support_phone') : ''),
            'إشعار إيقاف');
    }
    $suspended++;
}

/* ------------------------------------------ 3) إعادة تفعيل من سدّد */

$back = $pdo->query(
    "SELECT id, name FROM clinics
     WHERE status = 'suspended' AND expires_at IS NOT NULL AND expires_at >= CURDATE()"
)->fetchAll();
$revived = 0;

foreach ($back as $c) {
    printf("  إعادة تفعيل: %s\n", $c['name']);
    if (!$dry) {
        $pdo->prepare("UPDATE clinics SET status = 'active' WHERE id = ?")->execute([$c['id']]);
        $pdo->prepare('INSERT INTO console_log (user_id, action, entity, entity_id, summary, ip)
                       VALUES (NULL, ?, ?, ?, ?, ?)')
            ->execute(['auto_activate', 'clinic', (int)$c['id'],
                       'إعادة تفعيل بعد السداد: ' . $c['name'], 'cron']);
    }
    $revived++;
}

printf("\n---\nفواتير صادرة: %d | عيادات موقوفة: %d | أُعيد تفعيلها: %d\n",
    $issued, $suspended, $revived);

if ($issued && !$dry) {
    echo "راجع الفواتير في الكونسول وأرسل روابط السداد للعملاء.\n";
}
