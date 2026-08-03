<?php
/**
 * نقطة اتصال نسخ العيادات.
 *
 * ترسل النسخة رمزها فتُسجَّل حالتها (الإصدار، عدد المرضى، عدد المستخدمين)،
 * ويعود لها وضع اشتراكها لتعرض تنبيهًا للعيادة قبل الانتهاء.
 *
 * لا يعيد أي بيانات عن عيادات أخرى، ولا يقبل تعديل أي شيء عدا عدّادات النسخة.
 */
require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// POST فقط: الرمز مفتاح سري، ووضعه في رابط GET يسرّبه لسجلات السيرفر
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok' => false, 'error' => 'method']));
}
$token = trim((string)($_POST['token'] ?? ''));
if (strlen($token) < 20) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'token']));
}

$st = $pdo->prepare('SELECT * FROM clinics WHERE token = ?');
$st->execute([$token]);
$c = $st->fetch();
if (!$c) {
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'unknown']));
}

$pdo->prepare('UPDATE clinics SET last_ping_at = NOW(), app_version = ?, patients_count = ?, users_count = ?
               WHERE id = ?')
    ->execute([
        substr(trim((string)($_POST['version'] ?? '')), 0, 20),
        max(0, (int)($_POST['patients'] ?? 0)),
        max(0, (int)($_POST['users'] ?? 0)),
        (int)$c['id'],
    ]);

$days = days_until($c['expires_at']);
$grace = (int)setting('grace_days', '7');

// الحالة التي تعرضها نسخة العيادة لمستخدميها
$state = 'active';
$message = '';
if (in_array($c['status'], ['cancelled', 'suspended'], true)) {
    $state = 'suspended';
    $message = 'اشتراك النظام موقوف. برجاء التواصل معنا لإعادة التفعيل.';
} elseif ($days === null) {
    $state = 'active';
} elseif ($days < -$grace) {
    $state = 'expired';
    $message = 'انتهى اشتراك النظام. برجاء التجديد لمواصلة الدعم والتحديثات.';
} elseif ($days < 0) {
    $state = 'grace';
    $message = 'انتهى اشتراك النظام منذ ' . abs($days) . ' يوم — لديك مهلة '
             . ($grace + $days) . ' يوم للتجديد.';
} elseif ($days <= EXPIRY_WARN_DAYS) {
    $state = 'expiring';
    $message = 'اشتراك النظام ينتهي خلال ' . $days . ' يوم. جدّد قبل انتهائه.';
}

// أحدث فاتورة غير مسددة، ليعرض النظام رابط سدادها مباشرة للعيادة
$payUrl = '';
$st = $pdo->prepare("SELECT pay_token FROM invoices
                     WHERE clinic_id = ? AND status IN ('unpaid','partial')
                     ORDER BY id DESC LIMIT 1");
$st->execute([(int)$c['id']]);
if ($t = $st->fetchColumn()) {
    $base = rtrim(setting('console_url', ''), '/');
    $payUrl = $base !== '' ? $base . '/pay.php?t=' . $t : '';
}

echo json_encode([
    'ok'         => true,
    'state'      => $state,
    'status'     => $c['status'],
    'expires_at' => $c['expires_at'],
    'days_left'  => $days,
    'message'    => $message,
    'pay_url'    => $payUrl,
    'support'    => setting('support_phone', ''),
], JSON_UNESCAPED_UNICODE);
