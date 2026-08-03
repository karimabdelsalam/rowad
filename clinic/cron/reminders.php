<?php
/**
 * التذكير التلقائي — يُشغَّل يوميًا من Cron Jobs في cPanel.
 *
 * سطر الكرون (شغّله مرة واحدة يوميًا، مثلًا 6 مساءً):
 *   0 18 * * *  /usr/local/bin/php /home/USER/public_html/clinic/cron/reminders.php
 *
 * أو عبر رابط (لو استضافتك بتشغّل الكرون بـ wget/curl):
 *   0 18 * * *  curl -s "https://موقعك/clinic/cron/reminders.php?token=رمز_الكرون"
 *
 * رمز الكرون موجود في صفحة الإعدادات. الرابط بدون الرمز الصحيح يُرفض.
 *
 * التشغيل أكثر من مرة في نفس اليوم آمن: كل رسالة تُسجَّل في message_log
 * ولا تُرسل مرة أخرى لنفس المرجع في نفس اليوم.
 */
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';

require_once dirname(__DIR__) . '/inc/config.php';
require_once dirname(__DIR__) . '/inc/functions.php';
require_once dirname(__DIR__) . '/inc/tenant.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Cairo');
mb_internal_encoding('UTF-8');

/*
 * قد يكون الاتصال جاهزًا بالفعل حين يستدعينا مشغّل كل العيادات (reminders_all.php)
 * لعيادة بعينها؛ وإلا نفتح اتصال العيادة الواحدة.
 */
if (!isset($pdo) || !$pdo instanceof PDO) {
    try {
        $pdo = app_pdo();
    } catch (PDOException) {
        http_response_code(500);
        exit("تعذر الاتصال بقاعدة البيانات\n");
    }
}

require_once dirname(__DIR__) . '/inc/notify.php';

// حماية التشغيل عبر المتصفح برمز سري
if (!$isCli) {
    header('Content-Type: text/plain; charset=UTF-8');
    $token = setting('cron_token');
    if ($token === '' || !hash_equals($token, (string)($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit("رمز غير صالح\n");
    }
}

$today = date('Y-m-d');
$lead = max(0, (int)setting('notify_lead_days', '1'));
$targetDate = date('Y-m-d', strtotime("+$lead days"));
$log = [];
$sent = $failed = $skipped = 0;

$record = function (array $r) use (&$sent, &$failed, &$skipped, &$log): void {
    if ($r['ok']) {
        $sent++;
    } elseif (str_contains($r['error'], 'غير مفعّل') || str_contains($r['error'], 'اليدوي')) {
        $skipped++;
    } else {
        $failed++;
    }
    $log[] = $r;
};

/* ------------------------------------------------- 1) تذكير المواعيد */

$st = $pdo->prepare(
    "SELECT a.*, p.name AS pname, p.phone, p.id AS pid, u.name AS doctor_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     LEFT JOIN users u ON u.id = a.doctor_id
     WHERE a.adate = ? AND a.status = 'scheduled'"
);
$st->execute([$targetDate]);
$appointments = $st->fetchAll();

foreach ($appointments as $a) {
    if (notify_already_sent($pdo, 'appointment', (int)$a['id'], $today)) {
        continue;
    }
    $phone = wa_phone($a['phone']);
    if (!$phone) {
        notify_log($pdo, (int)$a['pid'], setting('notify_channel', 'whatsapp'), (string)$a['phone'],
                   '', 'skipped', 'رقم غير صالح', 'appointment', (int)$a['id']);
        $skipped++;
        continue;
    }
    $body = wa_message($a);
    if ($a['doctor_name']) {
        $body .= "\nمع: " . $a['doctor_name'];
    }
    $r = notify_send($pdo, (int)$a['pid'], $phone, $body, 'appointment', (int)$a['id']);
    $record($r + ['what' => 'موعد: ' . $a['pname']]);

    if ($r['ok']) {
        $pdo->prepare('UPDATE appointments SET reminder_sent = NOW() WHERE id = ?')->execute([(int)$a['id']]);
    }
}

/* --------------------------------------------- 2) تذكير جرعات الحقن */

$st = $pdo->prepare(
    "SELECT pl.id, pl.patient_id, pl.weekly_units, pl.start_date, p.name AS pname, p.phone, d.name AS drug_name,
        (SELECT MAX(dose_date) FROM injection_doses i WHERE i.plan_id = pl.id) AS last_dose
     FROM injection_plans pl
     JOIN patients p ON p.id = pl.patient_id
     JOIN drugs d ON d.id = pl.drug_id
     WHERE pl.status = 'active'"
);
$st->execute();

foreach ($st->fetchAll() as $pl) {
    $next = $pl['last_dose']
        ? date('Y-m-d', strtotime($pl['last_dose'] . ' +7 days'))
        : (string)$pl['start_date'];
    if ($next !== $targetDate) {
        continue;
    }
    if (notify_already_sent($pdo, 'injection', (int)$pl['id'], $today)) {
        continue;
    }
    $phone = wa_phone($pl['phone']);
    if (!$phone) {
        $skipped++;
        continue;
    }
    $body = 'مرحبًا ' . $pl['pname'] . " 🌿\n"
        . 'موعد جرعة ' . $pl['drug_name'] . ' يوم ' . day_ar($next) . ' الموافق ' . fmt_date($next) . ".\n"
        . 'الجرعة: ' . num_fmt($pl['weekly_units']) . " وحدة.\n"
        . 'برجاء تأكيد الحضور — ' . setting('clinic_name', 'العيادة');
    $record(notify_send($pdo, (int)$pl['patient_id'], $phone, $body, 'injection', (int)$pl['id'])
            + ['what' => 'جرعة: ' . $pl['pname']]);
}

/* ------------------------------------- 3) تنبيه الباقات المقاربة للانتهاء */

refresh_package_status($pdo);

$st = $pdo->prepare(
    "SELECT pp.*, p.name AS pname, p.phone,
        (SELECT COUNT(*) FROM package_uses u WHERE u.patient_package_id = pp.id) AS used
     FROM patient_packages pp JOIN patients p ON p.id = pp.patient_id
     WHERE pp.status = 'active' AND pp.expiry_date = ?"
);
$st->execute([date('Y-m-d', strtotime('+7 days'))]);

foreach ($st->fetchAll() as $pk) {
    $left = (int)$pk['sessions_total'] - (int)$pk['used'];
    if ($left < 1 || notify_already_sent($pdo, 'package', (int)$pk['id'], $today)) {
        continue;
    }
    $phone = wa_phone($pk['phone']);
    if (!$phone) {
        $skipped++;
        continue;
    }
    $body = 'مرحبًا ' . $pk['pname'] . " 🌿\n"
        . 'باقتك «' . $pk['name'] . '» تنتهي في ' . fmt_date($pk['expiry_date'])
        . ' ومتبقٍ بها ' . $left . " جلسة.\n"
        . 'يسعدنا حجز موعدك — ' . setting('clinic_name', 'العيادة');
    $record(notify_send($pdo, (int)$pk['patient_id'], $phone, $body, 'package', (int)$pk['id'])
            + ['what' => 'باقة: ' . $pk['pname']]);
}

/* ------------------------------------------------------------- التقرير */

$summary = sprintf(
    "[%s] تذكيرات %s — أُرسل: %d | فشل: %d | تخطّي: %d\n",
    date('Y-m-d H:i'), $targetDate, $sent, $failed, $skipped
);
echo $summary;
foreach ($log as $r) {
    if (!empty($r['what'])) {
        echo '  - ' . ($r['ok'] ? '✔' : '✘') . ' ' . $r['what']
           . ($r['error'] !== '' ? ' (' . $r['error'] . ')' : '') . "\n";
    }
}
if (!$log) {
    echo "  لا توجد تذكيرات مستحقة اليوم.\n";
}
