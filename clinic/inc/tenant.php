<?php
declare(strict_types=1);

/**
 * طبقة تعدد العيادات (SaaS) — قاعدة بيانات مستقلة لكل عيادة.
 *
 * لماذا قاعدة لكل عيادة وليس عمود tenant_id في كل جدول؟
 * لأن العزل هنا يحدث عند **الاتصال** لا عند كل استعلام. استعلام واحد يُنسى فيه
 * الفلتر في النموذج الآخر يعني عيادة ترى مرضى عيادة أخرى — وهذا في نظام طبي
 * ليس خطأً يُصلَح لاحقًا. هنا لا يوجد فلتر أصلًا يُنسى: كل عيادة قاعدتها.
 *
 * الوضع يُفعَّل بتعريف SAAS_MODE في inc/config.php. النسخ المُباعة كتركيب مستقل
 * لا تعرّفه فتعمل كما هي على قاعدة واحدة دون أي من هذا.
 */

/** أسماء نطاقات فرعية محجوزة لا تصلح لعيادة */
const RESERVED_SUBDOMAINS = [
    'www', 'api', 'app', 'admin', 'console', 'panel', 'dashboard', 'mail', 'ftp',
    'smtp', 'imap', 'pop', 'ns1', 'ns2', 'cpanel', 'webmail', 'blog', 'shop',
    'help', 'support', 'docs', 'status', 'static', 'assets', 'cdn', 'img',
    'test', 'dev', 'stage', 'staging', 'demo', 'signup', 'login', 'billing',
];

function saas_mode(): bool
{
    return defined('SAAS_MODE') && SAAS_MODE;
}

/** اتصال بقاعدة التحكم المركزية (نفس قاعدة الكونسول) */
function control_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = new PDO(
        'mysql:host=' . SAAS_DB_HOST . ';dbname=' . SAAS_DB_NAME . ';charset=utf8mb4',
        SAAS_DB_USER,
        SAAS_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    db_sync_timezone($pdo);
    return $pdo;
}

/** النطاق الفرعي المطلوب من ترويسة الطلب (أو null) */
function host_subdomain(?string $host = null): ?string
{
    $host = strtolower(trim($host ?? (string)($_SERVER['HTTP_HOST'] ?? '')));
    $host = explode(':', $host)[0];              // انزع المنفذ
    if ($host === '') {
        return null;
    }
    $base = strtolower(defined('SAAS_BASE_DOMAIN') ? SAAS_BASE_DOMAIN : '');
    if ($base !== '' && str_ends_with($host, '.' . $base)) {
        $sub = substr($host, 0, -(strlen($base) + 1));
        return $sub !== '' && !str_contains($sub, '.') ? $sub : null;
    }
    return null;
}

/**
 * يبحث عن العيادة المطلوبة بالنطاق الفرعي أو بنطاق مخصص.
 *
 * @return array|null صف العيادة من قاعدة التحكم
 */
function resolve_tenant(): ?array
{
    $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    $ctl = control_pdo();

    if ($sub = host_subdomain($host)) {
        $st = $ctl->prepare('SELECT * FROM clinics WHERE subdomain = ? AND db_name <> ""');
        $st->execute([$sub]);
        if ($row = $st->fetch()) {
            return $row;
        }
    }

    $st = $ctl->prepare('SELECT * FROM clinics WHERE custom_domain = ? AND db_name <> ""');
    $st->execute([$host]);
    return $st->fetch() ?: null;
}

/**
 * يوحّد ساعة MySQL مع ساعة PHP على الاتصال.
 *
 * بدون هذا يكتب `NOW()` بتوقيت السيرفر بينما يفلتر PHP بتوقيت التطبيق، فيختفي
 * سجل أُنشئ «اليوم» من تقرير «اليوم» كلما اختلف التوقيتان — وهو ما يحدث فعلًا
 * على أي سيرفر يعمل بـ UTC وتطبيق يعمل بتوقيت القاهرة.
 */
function db_sync_timezone(PDO $pdo): void
{
    $offset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
    try {
        $pdo->exec("SET time_zone = '$offset'");
    } catch (PDOException) {
        // بعض الاستضافات تمنع تغيير المنطقة الزمنية للجلسة — نكمل بالسلوك الافتراضي
    }
}

/** اتصال بقاعدة عيادة بعينها عبر مستخدم التطبيق الموحّد */
function tenant_pdo(string $dbName): PDO
{
    $pdo = new PDO(
        'mysql:host=' . SAAS_DB_HOST . ';dbname=' . $dbName . ';charset=utf8mb4',
        SAAS_DB_USER,
        SAAS_DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    db_sync_timezone($pdo);
    return $pdo;
}

/** اسم قاعدة البيانات العاملة حاليًا، في الوضعين */
function current_db_name(): string
{
    if (saas_mode()) {
        $t = tenant();
        return (string)($t['db_name'] ?? '');
    }
    return defined('DB_NAME') ? DB_NAME : '';
}

/**
 * اتصال بقاعدة عيادة يعمل في الوضعين.
 *
 * يستخدمه ما يعمل خارج دورة الطلب المعتادة: الكرون وبوابة المرضى.
 */
function app_pdo(?string $dbName = null): PDO
{
    if (saas_mode()) {
        return tenant_pdo($dbName ?? current_db_name());
    }
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . ($dbName ?? DB_NAME) . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
    db_sync_timezone($pdo);
    return $pdo;
}

/**
 * كل العيادات التي يجب أن تعمل عليها المهام المجدولة.
 *
 * في الوضع المستقل: قاعدة واحدة. في وضع SaaS: كل عيادة نشطة، فالتذكيرات
 * ترسَل لمرضى كل العيادات لا لعيادة واحدة.
 *
 * @return array<int, array{name:string, db:string}>
 */
function all_clinic_dbs(): array
{
    if (!saas_mode()) {
        return [['name' => 'العيادة', 'db' => DB_NAME]];
    }
    $rows = control_pdo()->query(
        "SELECT name, db_name FROM clinics
         WHERE db_name <> '' AND status IN ('trial','active') ORDER BY id"
    )->fetchAll();
    return array_map(fn($r) => ['name' => $r['name'], 'db' => $r['db_name']], $rows);
}

/** العيادة الحالية (تُملأ من bootstrap في وضع SaaS) */
function tenant(): ?array
{
    static $t = null;
    if (func_num_args() > 0) {
        $t = func_get_arg(0);
    }
    return $t;
}

/**
 * حالة اشتراك العيادة الحالية.
 *
 * @return array{state:string, days_left:?int, message:string, readonly:bool}
 */
function tenant_subscription(): array
{
    $t = tenant();
    if (!$t) {
        return ['state' => 'active', 'days_left' => null, 'message' => '', 'readonly' => false];
    }
    $grace = (int)(defined('SAAS_GRACE_DAYS') ? SAAS_GRACE_DAYS : 7);
    $days = null;
    if (!empty($t['expires_at'])) {
        $a = new DateTimeImmutable(date('Y-m-d'));
        $b = new DateTimeImmutable(date('Y-m-d', strtotime((string)$t['expires_at'])));
        $days = (int)$a->diff($b)->format('%r%a');
    }

    if (in_array($t['status'], ['suspended', 'cancelled'], true)) {
        return ['state' => 'suspended', 'days_left' => $days, 'readonly' => true,
                'message' => 'الاشتراك موقوف. البيانات كاملة أمامك ويمكن تصديرها، وإضافة بيانات جديدة تحتاج إعادة التفعيل.'];
    }
    if ($days === null) {
        return ['state' => 'active', 'days_left' => null, 'message' => '', 'readonly' => false];
    }
    if ($days < -$grace) {
        return ['state' => 'expired', 'days_left' => $days, 'readonly' => true,
                'message' => 'انتهى الاشتراك. بياناتك كلها موجودة ويمكنك تصديرها في أي وقت، وللإضافة والتعديل جدّد الاشتراك.'];
    }
    if ($days < 0) {
        return ['state' => 'grace', 'days_left' => $days, 'readonly' => false,
                'message' => 'انتهى الاشتراك منذ ' . abs($days) . ' يوم — لديك مهلة ' . ($grace + $days) . ' يوم قبل إيقاف الإضافة.'];
    }
    if ($days <= 14) {
        return ['state' => 'expiring', 'days_left' => $days, 'readonly' => false,
                'message' => 'اشتراكك ينتهي خلال ' . $days . ' يوم.'];
    }
    return ['state' => 'active', 'days_left' => $days, 'message' => '', 'readonly' => false];
}

/**
 * يمنع الكتابة بعد انتهاء المهلة.
 *
 * القراءة والتصدير والنسخ الاحتياطي تبقى مفتوحة دائمًا: بيانات المرضى ملك
 * العيادة، وحجبها عنها ضغطٌ غير مقبول في سجل طبي. المنع على الكتابة فقط،
 * ومن نقطة واحدة على السيرفر فلا يمكن تجاوزه بإخفاء زر.
 */
function tenant_guard_write(): void
{
    if (!saas_mode() || $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sub = tenant_subscription();
    if (!$sub['readonly']) {
        return;
    }
    // الخروج يبقى متاحًا حتى لا يعلق المستخدم داخل النظام
    if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'logout.php') {
        return;
    }
    http_response_code(402);
    $renew = defined('SAAS_BILLING_URL') ? SAAS_BILLING_URL : '';
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>الاشتراك منتهٍ</title><link rel="stylesheet" href="assets/clinic.css"></head>'
       . '<body class="auth-body"><div class="auth-card">'
       . '<h1 class="auth-title">الاشتراك منتهٍ</h1>'
       . '<div class="alert alert-warning">' . e($sub['message']) . '</div>'
       . ($renew !== '' ? '<a class="btn btn-block" href="' . e($renew) . '">تجديد الاشتراك</a>' : '')
       . '<a class="btn btn-light btn-block" href="index.php">العودة للنظام (عرض وتصدير)</a>'
       . '</div></body></html>';
    exit;
}

/** شريط حالة الاشتراك في وضع SaaS */
function tenant_banner(): void
{
    if (!saas_mode()) {
        return;
    }
    $sub = tenant_subscription();
    if ($sub['message'] === '') {
        return;
    }
    $type = $sub['state'] === 'expiring' ? 'warning' : 'danger';
    echo '<div class="alert alert-' . $type . '">' . e($sub['message']);
    if (defined('SAAS_BILLING_URL') && SAAS_BILLING_URL !== '') {
        echo ' <a href="' . e(SAAS_BILLING_URL) . '">تجديد الاشتراك ←</a>';
    }
    echo '</div>';
}
