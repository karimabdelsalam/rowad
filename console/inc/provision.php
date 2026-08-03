<?php
declare(strict_types=1);

/**
 * توفير عيادة جديدة: إنشاء قاعدتها وتجهيزها وربطها بنطاق فرعي.
 *
 * يستدعي نفس دالة البناء التي يستخدمها المثبّت اليدوي (clinic/inc/schema.php)
 * فلا تختلف قاعدة أُنشئت تلقائيًا عن أخرى أُنشئت يدويًا.
 */

/*
 * تُحمَّل وحدة السكيما وحدها دون دوال العيادة: الاثنان يعرّفان مساعدين بنفس
 * الأسماء (e، setting، page_header…) وتحميلهما معًا يتصادم.
 */
require_once __DIR__ . '/../../clinic/inc/schema.php';

/** النطاقات الفرعية المحجوزة (مُعرّفة أيضًا في clinic/inc/tenant.php) */
const RESERVED_SUBDOMAINS = [
    'www', 'api', 'app', 'admin', 'console', 'panel', 'dashboard', 'mail', 'ftp',
    'smtp', 'imap', 'pop', 'ns1', 'ns2', 'cpanel', 'webmail', 'blog', 'shop',
    'help', 'support', 'docs', 'status', 'static', 'assets', 'cdn', 'img',
    'test', 'dev', 'stage', 'staging', 'demo', 'signup', 'login', 'billing',
];

/** بادئة قواعد العيادات — يجب أن تطابق منحة MySQL الممنوحة لمستخدم التطبيق */
function tenant_db_prefix(): string
{
    return setting('tenant_prefix', 'clinic_');
}

/**
 * يتحقق أن اسم القاعدة آمن للإدراج في عبارة SQL.
 *
 * أسماء القواعد والجداول لا يمكن تمريرها كمعاملات مُجهَّزة، فتُدرَج نصًا.
 * لذلك يمر كل اسم من هنا قبل أي CREATE أو DROP أو اتصال — خاصةً DROP.
 */
function valid_db_name(string $name): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_]{1,64}$/', $name);
}

/** يتحقق من صلاحية النطاق الفرعي شكلًا */
function subdomain_valid(string $sub): bool
{
    return (bool)preg_match('/^[a-z0-9](?:[a-z0-9-]{1,28}[a-z0-9])$/', $sub);
}

/**
 * أسباب رفض النطاق الفرعي، أو مصفوفة فارغة إن كان صالحًا ومتاحًا.
 *
 * @return string[]
 */
function subdomain_errors(PDO $ctl, string $sub, ?int $exceptClinicId = null): array
{
    $errors = [];
    if (!subdomain_valid($sub)) {
        $errors[] = 'العنوان يجب أن يكون 3-30 حرفًا إنجليزيًا صغيرًا أو رقمًا أو شرطة، ولا يبدأ أو ينتهي بشرطة.';
        return $errors;
    }
    if (in_array($sub, RESERVED_SUBDOMAINS, true)) {
        $errors[] = 'هذا العنوان محجوز — اختر غيره.';
    }
    $sql = 'SELECT id FROM clinics WHERE subdomain = ?';
    $args = [$sub];
    if ($exceptClinicId) {
        $sql .= ' AND id <> ?';
        $args[] = $exceptClinicId;
    }
    $st = $ctl->prepare($sql);
    $st->execute($args);
    if ($st->fetchColumn()) {
        $errors[] = 'هذا العنوان مستخدم بالفعل — اختر غيره.';
    }
    return $errors;
}

/** اتصال بخادم MySQL دون تحديد قاعدة (لإنشاء القواعد) */
function server_pdo(): PDO
{
    return new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
}

/**
 * ينشئ قاعدة العيادة ويجهّزها.
 *
 * القاعدة تُنشأ أولًا ثم تُملأ؛ لو فشل الملء تُحذف القاعدة الفارغة حتى لا تتراكم
 * قواعد نصف مجهّزة تربك الترقيات والنسخ الاحتياطي.
 *
 * @param array{clinic_name:string, admin_name:string, admin_user:string, admin_pass:string,
 *              country_code?:string} $opt
 * @return string اسم القاعدة المُنشأة
 * @throws RuntimeException
 */
function provision_tenant_db(PDO $ctl, int $clinicId, array $opt): string
{
    $dbName = tenant_db_prefix() . $clinicId;
    if (!valid_db_name($dbName)) {
        throw new RuntimeException('اسم قاعدة غير صالح: ' . $dbName);
    }

    $srv = server_pdo();
    $exists = $srv->prepare('SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?');
    $exists->execute([$dbName]);
    if ($exists->fetchColumn()) {
        throw new RuntimeException('قاعدة بهذا الاسم موجودة بالفعل: ' . $dbName);
    }

    $srv->exec("CREATE DATABASE `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    try {
        $db = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . $dbName . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        build_clinic_db($db, $opt);
    } catch (Throwable $ex) {
        try {
            $srv->exec("DROP DATABASE `$dbName`");
        } catch (Throwable) {
            // لا نُخفي الخطأ الأصلي إن تعذّر التنظيف
        }
        throw new RuntimeException('فشل تجهيز قاعدة العيادة: ' . $ex->getMessage(), 0, $ex);
    }

    $ctl->prepare('UPDATE clinics SET db_name = ? WHERE id = ?')->execute([$dbName, $clinicId]);
    return $dbName;
}

/**
 * ينشئ عيادة كاملة: صف في قاعدة التحكم + قاعدة بيانات مجهّزة.
 *
 * @return array{clinic_id:int, db_name:string, url:string}
 */
function create_tenant(PDO $ctl, array $in): array
{
    $sub = strtolower(trim($in['subdomain']));
    if ($errs = subdomain_errors($ctl, $sub)) {
        throw new RuntimeException(implode(' ', $errs));
    }

    $trialDays = max(0, (int)setting('trial_days', '14'));
    $ctl->prepare('INSERT INTO clinics (name, owner_name, phone, email, subdomain, status,
                   start_date, expires_at, token, plan_id) VALUES (?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $in['clinic_name'], $in['owner_name'] ?? '', $in['phone'] ?? '', $in['email'] ?? '',
            $sub, 'trial', date('Y-m-d'),
            $trialDays > 0 ? date('Y-m-d', strtotime("+$trialDays days")) : null,
            bin2hex(random_bytes(24)),
            ((int)($in['plan_id'] ?? 0)) ?: null,
        ]);
    $clinicId = (int)$ctl->lastInsertId();

    try {
        $dbName = provision_tenant_db($ctl, $clinicId, [
            'clinic_name'  => $in['clinic_name'],
            'admin_name'   => $in['admin_name'],
            'admin_user'   => $in['admin_user'],
            'admin_pass'   => $in['admin_pass'],
            'country_code' => $in['country_code'] ?? '20',
        ]);
    } catch (Throwable $ex) {
        // لا نترك عيادة بلا قاعدة تظهر في القوائم
        $ctl->prepare('DELETE FROM clinics WHERE id = ?')->execute([$clinicId]);
        throw $ex;
    }

    return [
        'clinic_id' => $clinicId,
        'db_name'   => $dbName,
        'url'       => tenant_url($sub),
    ];
}

/** رابط عيادة من نطاقها الفرعي */
function tenant_url(string $sub): string
{
    $base = setting('base_domain', '');
    $scheme = setting('base_scheme', 'https');
    return $base !== '' ? $scheme . '://' . $sub . '.' . $base : '';
}

/**
 * يشغّل ترقية السكيما على كل قواعد العيادات.
 *
 * تُستدعى بعد كل تحديث للنظام: عيادة واحدة متأخرة في السكيما تعني أخطاء
 * استعلام عند أول استخدام، فالترقية تمر على الجميع وتُبلّغ عن كل فشل بمفرده.
 *
 * @return array<int, array{clinic:string, db:string, ok:bool, note:string}>
 */
function migrate_all_tenants(PDO $ctl): array
{
    $rows = $ctl->query("SELECT id, name, db_name FROM clinics WHERE db_name <> '' ORDER BY id")->fetchAll();
    $out = [];
    foreach ($rows as $r) {
        $entry = ['clinic' => $r['name'], 'db' => $r['db_name'], 'ok' => false, 'note' => ''];
        if (!valid_db_name((string)$r['db_name'])) {
            $entry['note'] = 'اسم قاعدة غير صالح — تخطّي';
            $out[] = $entry;
            continue;
        }
        try {
            $db = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . $r['db_name'] . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
            $before = (int)($db->query("SELECT svalue FROM settings WHERE skey='schema_version'")->fetchColumn() ?: 0);
            db_migrate($db);
            $after = (int)($db->query("SELECT svalue FROM settings WHERE skey='schema_version'")->fetchColumn() ?: 0);
            $entry['ok'] = true;
            $entry['note'] = $before === $after ? "محدّثة ($after)" : "من $before إلى $after";
        } catch (Throwable $ex) {
            $entry['note'] = $ex->getMessage();
        }
        $out[] = $entry;
    }
    return $out;
}
