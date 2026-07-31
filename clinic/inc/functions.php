<?php
declare(strict_types=1);

const ROLES = ['admin' => 'مدير', 'doctor' => 'أخصائي تغذية', 'reception' => 'استقبال'];

const APPT_TYPES  = ['new' => 'كشف جديد', 'followup' => 'متابعة', 'consult' => 'استشارة'];
const APPT_STATUS = ['scheduled' => 'محجوز', 'done' => 'تم', 'cancelled' => 'ملغي', 'no_show' => 'لم يحضر'];
const APPT_BADGE  = ['scheduled' => 'info', 'done' => 'ok', 'cancelled' => 'muted', 'no_show' => 'bad'];

const PAY_METHODS  = ['cash' => 'نقدي', 'card' => 'بطاقة', 'transfer' => 'تحويل بنكي', 'wallet' => 'محفظة إلكترونية'];
const EXPENSE_CATS = ['rent' => 'إيجار', 'salaries' => 'مرتبات', 'supplies' => 'مستلزمات', 'marketing' => 'تسويق', 'utilities' => 'مرافق وفواتير', 'other' => 'أخرى'];

const INJ_STATUS = ['active' => 'نشط', 'completed' => 'مكتمل', 'stopped' => 'موقوف'];
const INJ_BADGE  = ['active' => 'ok', 'completed' => 'muted', 'stopped' => 'bad'];
const INJ_SITES  = ['abdomen' => 'البطن', 'thigh' => 'الفخذ', 'arm' => 'الذراع', 'other' => 'أخرى'];

/** الخدمة المستخدمة في جدول المدفوعات لدخل الحقن */
const INJ_SERVICE = 'حقن تخسيس';

const AR_DAYS = [
    'Saturday' => 'السبت', 'Sunday' => 'الأحد', 'Monday' => 'الاثنين', 'Tuesday' => 'الثلاثاء',
    'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة',
];

function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

function csrf_verify(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('انتهت صلاحية الجلسة — ارجع للخلف وأعد المحاولة.');
    }
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['m' => $msg, 't' => $type];
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(user()['role'], $roles, true)) {
        page_header('غير مصرح');
        echo '<div class="card"><h2>غير مصرح</h2><p class="muted">ليست لديك صلاحية للوصول إلى هذه الصفحة.</p></div>';
        page_footer();
        exit;
    }
}

function has_role(string ...$roles): bool
{
    return user() !== null && in_array(user()['role'], $roles, true);
}

function &setting_cache(): ?array
{
    static $cache = null;
    return $cache;
}

function setting_flush(): void
{
    $cache = &setting_cache();
    $cache = null;
}

function setting(string $key, string $default = ''): string
{
    $cache = &setting_cache();
    if ($cache === null) {
        global $pdo;
        $cache = [];
        foreach ($pdo->query('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

function money(mixed $amount): string
{
    return number_format((float)$amount, 2) . ' ' . setting('currency', 'ج.م');
}

function fmt_date(?string $d): string
{
    return ($d && $d !== '0000-00-00') ? date('d/m/Y', strtotime($d)) : '—';
}

function fmt_time(?string $t): string
{
    if (!$t) {
        return '—';
    }
    $ts = strtotime($t);
    return date('g:i', $ts) . (date('a', $ts) === 'am' ? ' ص' : ' م');
}

function day_ar(string $date): string
{
    return AR_DAYS[date('l', strtotime($date))] ?? '';
}

const AR_MONTHS = [
    '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس', '04' => 'أبريل',
    '05' => 'مايو', '06' => 'يونيو', '07' => 'يوليو', '08' => 'أغسطس',
    '09' => 'سبتمبر', '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر',
];

/** 2026-07 → «يوليو 2026» */
function month_ar(string $ym): string
{
    return (AR_MONTHS[substr($ym, 5, 2)] ?? '') . ' ' . substr($ym, 0, 4);
}

function calc_age(?string $birth): ?int
{
    if (!$birth || $birth === '0000-00-00') {
        return null;
    }
    try {
        return (new DateTime($birth))->diff(new DateTime())->y;
    } catch (Exception) {
        return null;
    }
}

function calc_bmi(mixed $weight, mixed $height): ?float
{
    $w = (float)$weight;
    $h = (float)$height;
    if ($w <= 0 || $h <= 0) {
        return null;
    }
    return round($w / (($h / 100) ** 2), 1);
}

/** @return array{0:string,1:string} [label, badge-css] */
function bmi_label(float $bmi): array
{
    if ($bmi < 18.5) return ['نحافة', 'warn'];
    if ($bmi < 25)   return ['وزن طبيعي', 'ok'];
    if ($bmi < 30)   return ['زيادة وزن', 'warn'];
    if ($bmi < 35)   return ['سمنة (درجة أولى)', 'bad'];
    if ($bmi < 40)   return ['سمنة (درجة ثانية)', 'bad'];
    return ['سمنة مفرطة', 'bad'];
}

/* ------------------------------------------- حقن التخسيس والمحاسبة بالوحدات */

/** يعرض الأرقام بدون أصفار زائدة: 10.0 ← «10» و 7.5 ← «7.5» */
function num_fmt(mixed $n, int $decimals = 1): string
{
    $s = number_format((float)$n, $decimals, '.', '');
    return str_contains($s, '.') ? rtrim(rtrim($s, '0'), '.') : $s;
}

function units_fmt(mixed $units): string
{
    return num_fmt($units) . ' وحدة';
}

/** إجمالي الوحدات المتبقية في مخزون دواء معيّن */
function drug_units_left(PDO $pdo, int $drugId): float
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(units_total - units_used), 0) FROM drug_batches WHERE drug_id = ?');
    $st->execute([$drugId]);
    return (float)$st->fetchColumn();
}

/**
 * دفعات الدواء التي بها رصيد، مرتبة بالأقرب انتهاءً (صرف FIFO حسب الصلاحية)
 * حتى لا تنتهي صلاحية الأقلام في المخزن.
 */
function drug_batches_available(PDO $pdo, int $drugId): array
{
    $st = $pdo->prepare(
        'SELECT * FROM drug_batches
         WHERE drug_id = ? AND units_total > units_used
         ORDER BY (expiry_date IS NULL), expiry_date, id'
    );
    $st->execute([$drugId]);
    return $st->fetchAll();
}

/** رصيد المريض من الحقن: المستحق ناقص المدفوع (موجب = عليه متأخرات) */
function injection_balance(PDO $pdo, int $patientId): float
{
    $st = $pdo->prepare('SELECT COALESCE(SUM(amount - paid), 0) FROM injection_doses WHERE patient_id = ?');
    $st->execute([$patientId]);
    return round((float)$st->fetchColumn(), 2);
}

/** البروتوكول النشط للمريض (الدواء + الجرعة الأسبوعية + سعر الوحدة) */
function active_plan(PDO $pdo, int $patientId): ?array
{
    $st = $pdo->prepare(
        "SELECT p.*, d.name AS drug_name, d.units_per_pen
         FROM injection_plans p JOIN drugs d ON d.id = p.drug_id
         WHERE p.patient_id = ? AND p.status = 'active'
         ORDER BY p.start_date DESC, p.id DESC LIMIT 1"
    );
    $st->execute([$patientId]);
    return $st->fetch() ?: null;
}

/** موعد الجرعة القادمة = آخر جرعة + 7 أيام (أو بداية البروتوكول لو لسه مفيش جرعات) */
function next_dose_date(PDO $pdo, array $plan): string
{
    $st = $pdo->prepare('SELECT MAX(dose_date) FROM injection_doses WHERE plan_id = ?');
    $st->execute([(int)$plan['id']]);
    $last = $st->fetchColumn();
    return $last
        ? date('Y-m-d', strtotime($last . ' +7 days'))
        : (string)$plan['start_date'];
}

/** تكلفة الوحدة من دفعة معيّنة — لحساب ربح الحقن */
function batch_unit_cost(array $batch): float
{
    $units = (float)$batch['units_total'];
    return $units > 0 ? (float)$batch['cost_total'] / $units : 0.0;
}

/** قيمة المخزون المتبقي بسعر التكلفة — رأس مال «واقف» في الأقلام */
function stock_value(PDO $pdo): float
{
    $sql = 'SELECT COALESCE(SUM((units_total - units_used) * (cost_total / units_total)), 0)
            FROM drug_batches WHERE units_total > 0 AND units_total > units_used';
    return round((float)$pdo->query($sql)->fetchColumn(), 2);
}

/* ------------------------------------------------------------- واتساب */

/** رقم العيادة الدولي بدون + أو أصفار بادئة، أو null لو الرقم غير صالح */
function wa_phone(?string $phone, ?string $countryCode = null): ?string
{
    $cc = ltrim($countryCode ?? setting('country_code', '20'), '+0');
    $digits = preg_replace('/\D+/', '', (string)$phone) ?? '';
    if ($digits === '') {
        return null;
    }
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }
    if (str_starts_with($digits, '0')) {
        $digits = $cc . ltrim($digits, '0');
    } elseif ($cc !== '' && !str_starts_with($digits, $cc)) {
        $digits = $cc . $digits;
    }
    return strlen($digits) >= 10 ? $digits : null;
}

const WA_PLACEHOLDERS = [
    '{الاسم}'         => 'اسم المريض',
    '{اليوم}'         => 'اسم اليوم (السبت، الأحد…)',
    '{التاريخ}'       => 'تاريخ الموعد',
    '{الوقت}'         => 'وقت الموعد',
    '{النوع}'         => 'نوع الزيارة (كشف/متابعة)',
    '{العيادة}'       => 'اسم العيادة',
    '{هاتف_العيادة}' => 'هاتف العيادة',
    '{عنوان_العيادة}' => 'عنوان العيادة',
];

function wa_default_template(): string
{
    return "مرحبًا {الاسم} 🌿\n"
        . "نذكّركم بموعدكم في {العيادة} يوم {اليوم} الموافق {التاريخ} الساعة {الوقت}.\n"
        . "برجاء الحضور قبل الموعد بـ 10 دقائق.\n"
        . "لتأكيد أو تعديل الموعد: {هاتف_العيادة}";
}

/** يبني نص رسالة التذكير من القالب المحفوظ في الإعدادات */
function wa_message(array $appt, ?string $template = null): string
{
    $template = $template ?? setting('wa_template', wa_default_template());
    return strtr($template, [
        '{الاسم}'         => (string)($appt['pname'] ?? ''),
        '{اليوم}'         => day_ar((string)$appt['adate']),
        '{التاريخ}'       => fmt_date((string)$appt['adate']),
        '{الوقت}'         => fmt_time((string)$appt['atime']),
        '{النوع}'         => APPT_TYPES[$appt['type'] ?? ''] ?? '',
        '{العيادة}'       => setting('clinic_name', 'العيادة'),
        '{هاتف_العيادة}' => setting('clinic_phone'),
        '{عنوان_العيادة}' => setting('clinic_address'),
    ]);
}

/** رابط wa.me جاهز للفتح — يعمل مع واتساب على الموبايل والويب */
function wa_link(string $phoneDigits, string $message): string
{
    return 'https://wa.me/' . $phoneDigits . '?text=' . rawurlencode($message);
}

/* ------------------------------------------------------------- الترقية */

const SCHEMA_VERSION = 4;

const PKG_STATUS = ['active' => 'سارية', 'finished' => 'مستهلكة', 'expired' => 'منتهية', 'cancelled' => 'ملغاة'];
const PKG_BADGE  = ['active' => 'ok', 'finished' => 'muted', 'expired' => 'bad', 'cancelled' => 'muted'];
const MSG_CHANNELS = ['whatsapp' => 'واتساب', 'sms' => 'رسالة نصية', 'email' => 'بريد إلكتروني'];

/** جداول الباقات والرسائل — مشتركة بين التثبيت الجديد والترقية */
function packages_schema(): array
{
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS packages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            sessions INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            validity_days INT NOT NULL DEFAULT 90,
            includes TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $opts",
        "CREATE TABLE IF NOT EXISTS patient_packages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            package_id INT UNSIGNED NULL,
            name VARCHAR(150) NOT NULL,
            sessions_total INT NOT NULL,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            paid DECIMAL(10,2) NOT NULL DEFAULT 0,
            start_date DATE NOT NULL,
            expiry_date DATE NULL,
            status ENUM('active','finished','expired','cancelled') NOT NULL DEFAULT 'active',
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
            INDEX idx_patient (patient_id, status)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS package_uses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_package_id INT UNSIGNED NOT NULL,
            use_date DATE NOT NULL,
            appointment_id INT UNSIGNED NULL,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_package_id) REFERENCES patient_packages(id) ON DELETE CASCADE,
            FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
            INDEX idx_pkg (patient_package_id)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS message_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NULL,
            channel VARCHAR(20) NOT NULL DEFAULT 'whatsapp',
            target VARCHAR(120) NOT NULL DEFAULT '',
            body TEXT NULL,
            status ENUM('sent','failed','skipped') NOT NULL DEFAULT 'sent',
            error VARCHAR(255) NOT NULL DEFAULT '',
            ref_type VARCHAR(30) NOT NULL DEFAULT '',
            ref_id INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ref (ref_type, ref_id),
            INDEX idx_created (created_at)
        ) $opts",
    ];
}

/* ------------------------------------------------- تعدد الأطباء والصلاحيات */

/** قائمة الأطباء (تشمل المدير لأنه غالبًا الطبيب في العيادات الصغيرة) */
function doctors_list(PDO $pdo): array
{
    return $pdo->query(
        "SELECT id, name, role FROM users WHERE active = 1 AND role IN ('doctor','admin') ORDER BY role='doctor' DESC, name"
    )->fetchAll();
}

/** هل يرى الطبيب الحالي مرضاه فقط؟ */
function doctor_scoped(): bool
{
    return has_role('doctor') && setting('doctor_scope', 'own') === 'own';
}

/**
 * شرط SQL لتقييد النتائج على مرضى الطبيب الحالي.
 * @return array{0:string,1:array} [جزء الشرط, المعاملات]
 */
function doctor_filter(string $alias = 'p'): array
{
    if (!doctor_scoped()) {
        return ['', []];
    }
    return [" AND $alias.doctor_id = ?", [user()['id']]];
}

/** هل يملك المستخدم الحالي صلاحية على ملف هذا المريض؟ */
function can_access_patient(array $patient): bool
{
    if (!doctor_scoped()) {
        return true;
    }
    return (int)($patient['doctor_id'] ?? 0) === (int)user()['id'];
}

/* --------------------------------------------------------- باقات الجلسات */

/** عدد الجلسات المستهلكة من باقة */
function package_used(PDO $pdo, int $patientPackageId): int
{
    $st = $pdo->prepare('SELECT COUNT(*) FROM package_uses WHERE patient_package_id = ?');
    $st->execute([$patientPackageId]);
    return (int)$st->fetchColumn();
}

/** الباقات السارية للمريض مع عدد الجلسات المتبقية */
function patient_active_packages(PDO $pdo, int $patientId): array
{
    $st = $pdo->prepare(
        "SELECT pp.*, (SELECT COUNT(*) FROM package_uses u WHERE u.patient_package_id = pp.id) AS used
         FROM patient_packages pp
         WHERE pp.patient_id = ? AND pp.status = 'active'
         ORDER BY pp.expiry_date IS NULL, pp.expiry_date, pp.id"
    );
    $st->execute([$patientId]);
    return array_filter($st->fetchAll(), fn($p) => (int)$p['used'] < (int)$p['sessions_total']);
}

/** يُغلق الباقات المستهلكة أو المنتهية الصلاحية — يُستدعى من الكرون ومن صفحة الباقات */
function refresh_package_status(PDO $pdo): int
{
    $n = $pdo->exec(
        "UPDATE patient_packages pp SET pp.status = 'finished'
         WHERE pp.status = 'active'
           AND (SELECT COUNT(*) FROM package_uses u WHERE u.patient_package_id = pp.id) >= pp.sessions_total"
    );
    $n += $pdo->exec(
        "UPDATE patient_packages SET status = 'expired'
         WHERE status = 'active' AND expiry_date IS NOT NULL AND expiry_date < CURDATE()"
    );
    return (int)$n;
}

/** جمل إنشاء جداول الحقن — مشتركة بين التثبيت الجديد والترقية */
function injection_schema(): array
{
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS drugs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            units_per_pen DECIMAL(8,1) NOT NULL DEFAULT 300,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            cost_per_pen DECIMAL(10,2) NOT NULL DEFAULT 0,
            low_units DECIMAL(8,1) NOT NULL DEFAULT 100,
            active TINYINT(1) NOT NULL DEFAULT 1,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $opts",
        "CREATE TABLE IF NOT EXISTS drug_batches (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            drug_id INT UNSIGNED NOT NULL,
            batch_no VARCHAR(60) NOT NULL DEFAULT '',
            expiry_date DATE NULL,
            pens DECIMAL(8,1) NOT NULL DEFAULT 1,
            units_total DECIMAL(10,1) NOT NULL DEFAULT 0,
            units_used DECIMAL(10,1) NOT NULL DEFAULT 0,
            cost_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            received_date DATE NOT NULL,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            FOREIGN KEY (drug_id) REFERENCES drugs(id) ON DELETE CASCADE,
            INDEX idx_drug (drug_id, expiry_date)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS injection_plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            drug_id INT UNSIGNED NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            weekly_units DECIMAL(8,1) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            status ENUM('active','completed','stopped') NOT NULL DEFAULT 'active',
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (drug_id) REFERENCES drugs(id),
            INDEX idx_patient (patient_id, status)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS injection_doses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            plan_id INT UNSIGNED NULL,
            drug_id INT UNSIGNED NOT NULL,
            batch_id INT UNSIGNED NULL,
            dose_date DATE NOT NULL,
            units DECIMAL(8,1) NOT NULL,
            unit_price DECIMAL(10,2) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            paid DECIMAL(10,2) NOT NULL DEFAULT 0,
            unit_cost DECIMAL(10,4) NOT NULL DEFAULT 0,
            site ENUM('abdomen','thigh','arm','other') NOT NULL DEFAULT 'abdomen',
            notes VARCHAR(255) NOT NULL DEFAULT '',
            given_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            FOREIGN KEY (plan_id) REFERENCES injection_plans(id) ON DELETE SET NULL,
            FOREIGN KEY (drug_id) REFERENCES drugs(id),
            FOREIGN KEY (batch_id) REFERENCES drug_batches(id) ON DELETE SET NULL,
            INDEX idx_patient (patient_id, dose_date),
            INDEX idx_date (dose_date)
        ) $opts",
    ];
}

/**
 * ترقية بنية قاعدة البيانات للتركيبات القديمة — تعمل مرة واحدة فقط
 * لأن الفحص يقرأ من الإعدادات المُحمّلة أصلًا في الذاكرة.
 */
function db_migrate(PDO $pdo): void
{
    try {
        $current = (int)setting('schema_version', '1');
    } catch (PDOException) {
        return; // النظام غير مثبت بعد
    }
    if ($current >= SCHEMA_VERSION) {
        return;
    }

    if ($current < 2) {
        $cols = $pdo->query("SHOW COLUMNS FROM appointments LIKE 'reminder_sent'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE appointments ADD COLUMN reminder_sent DATETIME NULL');
        }
        $st = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
        $st->execute(['country_code', '20']);
        $st->execute(['wa_template', wa_default_template()]);
    }

    if ($current < 3) {
        foreach (injection_schema() as $sql) {
            $pdo->exec($sql);
        }
        $cols = $pdo->query("SHOW COLUMNS FROM payments LIKE 'dose_id'")->fetchAll();
        if (!$cols) {
            $pdo->exec('ALTER TABLE payments ADD COLUMN dose_id INT UNSIGNED NULL');
            $pdo->exec('ALTER TABLE payments ADD CONSTRAINT fk_pay_dose FOREIGN KEY (dose_id)
                        REFERENCES injection_doses(id) ON DELETE CASCADE');
        }
    }

    if ($current < 4) {
        foreach (packages_schema() as $sql) {
            $pdo->exec($sql);
        }
        $addCol = function (string $table, string $col, string $def) use ($pdo): void {
            if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col))->fetchAll()) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        };
        $addCol('patients', 'doctor_id', 'INT UNSIGNED NULL');
        $addCol('patients', 'portal_password', 'VARCHAR(255) NULL');
        $addCol('patients', 'portal_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
        $addCol('appointments', 'doctor_id', 'INT UNSIGNED NULL');
        $addCol('appointments', 'patient_package_id', 'INT UNSIGNED NULL');

        $st = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
        foreach ([
            'doctor_scope'     => 'own',
            'notify_enabled'   => '0',
            'notify_channel'   => 'whatsapp',
            'notify_provider'  => 'webhook',
            'notify_url'       => '',
            'notify_token'     => '',
            'notify_sender'    => '',
            'notify_lead_days' => '1',
            'cron_token'       => bin2hex(random_bytes(16)),
            'portal_enabled'   => '1',
        ] as $k => $v) {
            $st->execute([$k, $v]);
        }
    }

    $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
        ->execute(['schema_version', (string)SCHEMA_VERSION]);
    setting_flush();
}

function patient_options(PDO $pdo): array
{
    return $pdo->query('SELECT id, code, name, phone FROM patients ORDER BY name')->fetchAll();
}

/** datalist للبحث عن مريض بالاسم أو الكود أو الهاتف؛ الحقل المخفي يحمل الرقم */
function patient_picker(PDO $pdo, string $inputName = 'patient_id', ?int $selected = null, bool $required = true): string
{
    $rows = patient_options($pdo);
    $listId = 'pl_' . $inputName;
    $selText = '';
    $out = '<input list="' . e($listId) . '" name="' . e($inputName) . '_text" class="patient-pick" data-target="' . e($inputName) . '" placeholder="ابحث بالاسم أو الهاتف…" autocomplete="off"' . ($required ? ' required' : '') . ' value="';
    $data = '<datalist id="' . e($listId) . '">';
    foreach ($rows as $r) {
        $label = $r['name'] . ' — ' . $r['code'] . ($r['phone'] ? ' — ' . $r['phone'] : '');
        if ($selected !== null && (int)$r['id'] === $selected) {
            $selText = $label;
        }
        $data .= '<option data-id="' . (int)$r['id'] . '" value="' . e($label) . '"></option>';
    }
    $data .= '</datalist>';
    $out .= e($selText) . '">' . $data;
    $out .= '<input type="hidden" name="' . e($inputName) . '" id="' . e($inputName) . '" value="' . ($selected !== null ? $selected : '') . '">';
    return $out;
}

/** يقرأ رقم المريض من الحقل المخفي، أو يحلّه من النص المكتوب كخطة بديلة */
function posted_patient_id(PDO $pdo, string $inputName = 'patient_id'): ?int
{
    $id = (int)($_POST[$inputName] ?? 0);
    if ($id > 0) {
        return $id;
    }
    return resolve_patient_id($pdo, (string)($_POST[$inputName . '_text'] ?? ''));
}

/** يحوّل نص الـ datalist المختار إلى رقم المريض */
function resolve_patient_id(PDO $pdo, string $text): ?int
{
    if (preg_match('/—\s*(P-\d+)\s*(?:—|$)/u', $text, $m)) {
        $st = $pdo->prepare('SELECT id FROM patients WHERE code = ?');
        $st->execute([trim($m[1])]);
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }
    $st = $pdo->prepare('SELECT id FROM patients WHERE name = ? LIMIT 2');
    $st->execute([trim($text)]);
    $ids = $st->fetchAll(PDO::FETCH_COLUMN);
    return count($ids) === 1 ? (int)$ids[0] : null;
}

/* ---------------------------------------------------------------- التخطيط */

function page_header(string $title, string $active = ''): void
{
    $u = user();
    $clinic = setting('clinic_name', 'عيادة التغذية');
    $nav = [
        ['index.php',        'لوحة التحكم',      '🏠', ['admin', 'doctor', 'reception']],
        ['appointments.php', 'المواعيد',          '📅', ['admin', 'doctor', 'reception']],
        ['reminders.php',    'تذكير واتساب',      '💬', ['admin', 'doctor', 'reception']],
        ['patients.php',     'المرضى',            '👥', ['admin', 'doctor', 'reception']],
        ['plans.php',        'الأنظمة الغذائية',  '🥗', ['admin', 'doctor', 'reception']],
        ['injections.php',   'الحقن',             '💉', ['admin', 'doctor', 'reception']],
        ['drugs.php',        'الأدوية والمخزون',  '📦', ['admin', 'doctor']],
        ['packages.php',     'باقات الجلسات',     '🎟️', ['admin', 'doctor', 'reception']],
        ['payments.php',     'المدفوعات',         '💰', ['admin', 'reception']],
        ['expenses.php',     'المصروفات',         '🧾', ['admin']],
        ['reports.php',      'التقارير',          '📈', ['admin']],
        ['users.php',        'المستخدمون',        '👤', ['admin']],
        ['settings.php',     'الإعدادات',         '⚙️', ['admin']],
    ];
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' | ' . e($clinic) . '</title>';
    echo '<link rel="stylesheet" href="assets/clinic.css">';
    echo '</head><body><div class="layout">';

    echo '<aside class="sidebar"><div class="brand">🍏 ' . e($clinic) . '</div><nav>';
    foreach ($nav as [$href, $label, $icon, $roles]) {
        if ($u && !in_array($u['role'], $roles, true)) {
            continue;
        }
        $cls = $active === $href ? ' class="active"' : '';
        echo '<a href="' . e($href) . '"' . $cls . '><span class="ico">' . $icon . '</span> ' . e($label) . '</a>';
    }
    echo '</nav></aside>';

    echo '<div class="main"><header class="topbar"><h1>' . e($title) . '</h1>';
    if ($u) {
        echo '<div class="userbox"><span><strong>' . e($u['name']) . '</strong> <small class="muted">(' . e(ROLES[$u['role']] ?? $u['role']) . ')</small></span>';
        echo '<a class="btn btn-light btn-sm" href="logout.php">خروج</a></div>';
    }
    echo '</header><main class="content">';

    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<div class="alert alert-' . e($f['t']) . '">' . e($f['m']) . '</div>';
    }
    unset($_SESSION['flash']);
}

function page_footer(): void
{
    echo '</main></div></div>';
    echo '<script src="assets/clinic.js"></script>';
    echo '</body></html>';
}

/* -------------------------------------------------- رسم تطور الوزن (SVG) */

/** @param array<array{mdate:string, weight:string|float}> $rows تصاعدي بالتاريخ */
function weight_chart_svg(array $rows): string
{
    $n = count($rows);
    if ($n < 2) {
        return '<p class="muted">سجّل قياسين على الأقل لعرض منحنى الوزن.</p>';
    }
    $W = 720; $H = 250;
    $padL = 46; $padR = 26; $padT = 20; $padB = 34;
    $plotW = $W - $padL - $padR;
    $plotH = $H - $padT - $padB;

    $ws = array_map(fn($r) => (float)$r['weight'], $rows);
    $min = floor(min($ws)) - 1.0;
    $max = ceil(max($ws)) + 1.0;
    if ($max - $min < 4) {
        $max = $min + 4;
    }
    $x = fn(int $i): float => $padL + $plotW * ($n > 1 ? $i / ($n - 1) : 0.5);
    $y = fn(float $v): float => $padT + $plotH * (1 - ($v - $min) / ($max - $min));

    $svg = '<div class="chart-wrap" dir="ltr"><svg viewBox="0 0 ' . $W . ' ' . $H . '" role="img" aria-label="منحنى تطور الوزن">';

    // شبكة أفقية خفيفة + قيم المحور
    for ($g = 0; $g <= 4; $g++) {
        $val = $min + ($max - $min) * $g / 4;
        $gy = $y($val);
        $svg .= sprintf('<line x1="%d" y1="%.1f" x2="%d" y2="%.1f" stroke="#e2e8f0" stroke-width="1"/>', $padL, $gy, $W - $padR, $gy);
        $svg .= sprintf('<text x="%d" y="%.1f" font-size="11" fill="#64748b" text-anchor="end">%.0f</text>', $padL - 8, $gy + 4, $val);
    }

    // الخط
    $pts = [];
    foreach ($ws as $i => $w) {
        $pts[] = sprintf('%.1f,%.1f', $x($i), $y($w));
    }
    $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="#0f766e" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';

    // النقاط + تلميح عند المرور
    foreach ($ws as $i => $w) {
        $tip = fmt_date($rows[$i]['mdate']) . ' — ' . rtrim(rtrim(number_format($w, 1), '0'), '.') . ' كجم';
        $svg .= sprintf(
            '<circle cx="%.1f" cy="%.1f" r="4.5" fill="#0f766e" stroke="#fff" stroke-width="2"><title>%s</title></circle>',
            $x($i), $y($w), e($tip)
        );
    }

    // تسمية مباشرة لأول وآخر قيمة فقط
    foreach ([0, $n - 1] as $i) {
        $lbl = rtrim(rtrim(number_format($ws[$i], 1), '0'), '.');
        $svg .= sprintf(
            '<text x="%.1f" y="%.1f" font-size="12" font-weight="700" fill="#0f172a" text-anchor="middle">%s</text>',
            $x($i), $y($ws[$i]) - 10, e($lbl)
        );
    }

    // تواريخ المحور الأفقي: الأول والوسط والأخير
    foreach (array_unique([0, intdiv($n - 1, 2), $n - 1]) as $i) {
        $svg .= sprintf(
            '<text x="%.1f" y="%d" font-size="11" fill="#64748b" text-anchor="middle">%s</text>',
            $x($i), $H - 10, e(date('d/m', strtotime($rows[$i]['mdate'])))
        );
    }

    return $svg . '</svg></div>';
}
