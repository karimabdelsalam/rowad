<?php
declare(strict_types=1);

/**
 * فحص الاشتراك مقابل كونسول المزوّد (اختياري تمامًا).
 *
 * يعمل فقط لو عُرّف LICENSE_URL و LICENSE_TOKEN في inc/config.php. النسخ التي
 * تُباع كرخصة دائمة لا تعرّفهما فلا يحدث أي اتصال خارجي.
 *
 * مبادئ مقصودة:
 *  - **لا يُقفل النظام أبدًا.** بيانات المرضى ملك العيادة، وأقصى ما يحدث عند
 *    انتهاء الاشتراك هو تنبيه ظاهر. الحجب الكامل لسجل طبي خطر مهني وقانوني.
 *  - **فشل الاتصال لا يعني انتهاء الاشتراك.** انقطاع الشبكة أو تعطّل الكونسول
 *    يُتجاهل ويُعاد المحفوظ، فلا تتعطل عيادة بسبب مشكلة عندنا.
 *  - الفحص مرة يوميًا فقط، وبمهلة قصيرة، حتى لا يبطئ أي صفحة.
 */

const LICENSE_CHECK_EVERY = 86400;   // مرة كل 24 ساعة
const LICENSE_TIMEOUT     = 4;       // ثوانٍ

function license_enabled(): bool
{
    return defined('LICENSE_URL') && defined('LICENSE_TOKEN')
        && LICENSE_URL !== '' && LICENSE_TOKEN !== '';
}

/** آخر رد محفوظ من الكونسول (أو null) */
function license_state(PDO $pdo): ?array
{
    if (!license_enabled()) {
        return null;
    }
    $raw = setting('license_state', '');
    $data = $raw !== '' ? json_decode($raw, true) : null;
    return is_array($data) ? $data : null;
}

/**
 * يتصل بالكونسول إن مرّ يوم على آخر اتصال. يُستدعى من bootstrap بعد تسجيل
 * الدخول فقط، حتى لا يتصل بشيء قبل أن يستخدم أحدٌ النظام.
 */
function license_refresh(PDO $pdo): void
{
    if (!license_enabled() || !function_exists('curl_init')) {
        return;
    }
    $last = (int)setting('license_checked_at', '0');
    if (time() - $last < LICENSE_CHECK_EVERY) {
        return;
    }

    // يُحدَّث الوقت قبل المحاولة، فلا تتكرر المحاولة كل طلب لو كان الكونسول متعطلًا
    $save = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                           ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    $save->execute(['license_checked_at', (string)time()]);

    $patients = 0;
    $users = 0;
    try {
        $patients = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();
        $users = (int)$pdo->query('SELECT COUNT(*) FROM users WHERE active = 1')->fetchColumn();
    } catch (Throwable) {
        // العدّادات إضافية؛ فشلها لا يمنع الاتصال
    }

    $ch = curl_init(LICENSE_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'token'    => LICENSE_TOKEN,
            'version'  => (string)SCHEMA_VERSION,
            'patients' => $patients,
            'users'    => $users,
        ]),
        CURLOPT_TIMEOUT        => LICENSE_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => LICENSE_TIMEOUT,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $code !== 200) {
        return;   // نُبقي آخر حالة معروفة
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data) || empty($data['ok'])) {
        return;
    }

    $save->execute(['license_state', json_encode([
        'state'      => (string)($data['state'] ?? 'active'),
        'message'    => (string)($data['message'] ?? ''),
        'days_left'  => $data['days_left'] ?? null,
        'expires_at' => (string)($data['expires_at'] ?? ''),
        'pay_url'    => (string)($data['pay_url'] ?? ''),
        'support'    => (string)($data['support'] ?? ''),
    ], JSON_UNESCAPED_UNICODE)]);
    setting_flush();
}

/** شريط التنبيه أعلى الصفحات — يُعرض للمدير فقط ولا يمنع أي إجراء */
function license_banner(PDO $pdo): void
{
    $s = license_state($pdo);
    if (!$s || in_array($s['state'], ['active', ''], true) || ($s['message'] ?? '') === '') {
        return;
    }
    $type = match ($s['state']) {
        'expiring' => 'warning',
        default    => 'danger',
    };
    echo '<div class="alert alert-' . e($type) . '">' . e($s['message']);
    if (!empty($s['pay_url'])) {
        echo ' <a href="' . e($s['pay_url']) . '" target="_blank" rel="noopener">تجديد الاشتراك ←</a>';
    }
    if (!empty($s['support'])) {
        echo ' <span class="muted">— للتواصل: ' . e($s['support']) . '</span>';
    }
    echo '</div>';
}
