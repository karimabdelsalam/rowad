<?php
declare(strict_types=1);

/**
 * كونسول إدارة الاشتراكات — الدوال الأساسية.
 *
 * تطبيق منفصل تمامًا عن نظام العيادة: قاعدة بيانات مستقلة وجلسة مستقلة، لأن
 * بياناته تجارية (عملاء واشتراكات وتحصيل) ولا يصح أن تختلط ببيانات المرضى.
 */

const CONSOLE_SCHEMA_VERSION = 2;

const CLINIC_STATUS = [
    'trial'     => 'تجريبي',
    'active'    => 'مشترك',
    'suspended' => 'موقوف',
    'cancelled' => 'ملغي',
];
const CLINIC_STATUS_BADGE = [
    'trial' => 'warn', 'active' => 'ok', 'suspended' => 'bad', 'cancelled' => 'muted',
];

const INV_STATUS = [
    'unpaid'  => 'غير مدفوعة',
    'partial' => 'مدفوعة جزئيًا',
    'paid'    => 'مدفوعة',
    'void'    => 'ملغاة',
];
const INV_STATUS_BADGE = ['unpaid' => 'bad', 'partial' => 'warn', 'paid' => 'ok', 'void' => 'muted'];

const PAY_METHODS = [
    'cash'     => 'كاش',
    'instapay' => 'إنستا باي',
    'paymob'   => 'باي موب (أونلاين)',
];
const PAY_STATUS = ['pending' => 'بانتظار التأكيد', 'confirmed' => 'مؤكدة', 'failed' => 'فاشلة'];
const PAY_STATUS_BADGE = ['pending' => 'warn', 'confirmed' => 'ok', 'failed' => 'bad'];

/** عدد الأيام التي يُنبَّه فيها قبل انتهاء الاشتراك */
const EXPIRY_WARN_DAYS = 14;

/* ------------------------------------------------------------ مساعدات */

function e(mixed $v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
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

function cuser(): ?array
{
    return $_SESSION['cuser'] ?? null;
}

function require_login(): void
{
    if (!cuser()) {
        redirect('login.php');
    }
}

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        global $pdo;
        $cache = [];
        foreach ($pdo->query('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return ($cache[$key] ?? '') !== '' ? $cache[$key] : $default;
}

function setting_flush(): void
{
    // إعادة تحميل الإعدادات بعد الحفظ تتم بإعادة التوجيه، وهذه للاستخدام المباشر
    global $pdo;
    $pdo->query('SELECT 1');
}

function money(mixed $amount): string
{
    return number_format((float)$amount, 2) . ' ' . setting('currency', 'ج.م');
}

function fmt_date(?string $d): string
{
    return $d ? date('Y/m/d', strtotime($d)) : '—';
}

/** أيام متبقية حتى تاريخ (سالبة إن مضى) */
function days_until(?string $date): ?int
{
    if (!$date) {
        return null;
    }
    $a = new DateTimeImmutable(date('Y-m-d'));
    $b = new DateTimeImmutable(date('Y-m-d', strtotime($date)));
    return (int)$a->diff($b)->format('%r%a');
}

function log_action(PDO $pdo, string $action, string $entity, ?int $entityId, string $summary): void
{
    $pdo->prepare('INSERT INTO console_log (user_id, action, entity, entity_id, summary, ip)
                   VALUES (?,?,?,?,?,?)')
        ->execute([
            cuser()['id'] ?? null, $action, $entity, $entityId, $summary,
            substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
        ]);
}

/* ------------------------------------------------------- منطق الاشتراك */

/**
 * يعيد حساب حالة الفاتورة من مدفوعاتها المؤكدة، ويمدّ اشتراك العيادة مرة واحدة
 * فقط عند اكتمال السداد.
 *
 * المدّ محكوم بعلم `applied` حتى لا يتكرر لو أُعيد تأكيد دفعة، ويُعكس بنفس عدد
 * الشهور لو رجعت الفاتورة غير مكتملة (بحذف دفعة أو إلغاء تأكيدها).
 */
function invoice_recalc(PDO $pdo, int $invoiceId): void
{
    $st = $pdo->prepare('SELECT * FROM invoices WHERE id = ? FOR UPDATE');
    $st->execute([$invoiceId]);
    $inv = $st->fetch();
    if (!$inv || $inv['status'] === 'void') {
        return;
    }

    $st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments
                         WHERE invoice_id = ? AND status = 'confirmed'");
    $st->execute([$invoiceId]);
    $paid = (float)$st->fetchColumn();
    $amount = (float)$inv['amount'];

    // فرق أقل من قرش يُعتبر سدادًا كاملًا
    $isPaid = $paid + 0.005 >= $amount;
    $status = $isPaid ? 'paid' : ($paid > 0.005 ? 'partial' : 'unpaid');

    $applied = (int)$inv['applied'];
    $months = max(0, (int)$inv['months']);

    if ($isPaid && !$applied && $months > 0) {
        clinic_extend($pdo, (int)$inv['clinic_id'], $months);
        $applied = 1;
    } elseif (!$isPaid && $applied && $months > 0) {
        clinic_extend($pdo, (int)$inv['clinic_id'], -$months);
        $applied = 0;
    }

    $pdo->prepare('UPDATE invoices SET status = ?, paid = ?, applied = ? WHERE id = ?')
        ->execute([$status, $paid, $applied, $invoiceId]);
}

/**
 * يمدّ اشتراك العيادة بعدد شهور (سالب = تراجع).
 *
 * المدّ يبدأ من تاريخ الانتهاء الحالي إن كان الاشتراك ساريًا، ومن اليوم إن كان
 * منتهيًا — حتى لا يضيع على العميل ما تبقّى له، ولا يُحسب له تجديد رجعي.
 */
function clinic_extend(PDO $pdo, int $clinicId, int $months): void
{
    $st = $pdo->prepare('SELECT expires_at, status FROM clinics WHERE id = ? FOR UPDATE');
    $st->execute([$clinicId]);
    $c = $st->fetch();
    if (!$c) {
        return;
    }
    $today = date('Y-m-d');
    $base = ($c['expires_at'] && $c['expires_at'] > $today) ? $c['expires_at'] : $today;
    $new = date('Y-m-d', strtotime($base . ' ' . ($months >= 0 ? '+' : '-') . abs($months) . ' months'));

    $status = $c['status'];
    if ($months > 0 && in_array($status, ['trial', 'suspended'], true)) {
        $status = 'active';
    }
    $pdo->prepare('UPDATE clinics SET expires_at = ?, status = ? WHERE id = ?')
        ->execute([$new, $status, $clinicId]);
}

/** رقم فاتورة تسلسلي بصيغة INV-YYYY-0001 */
function next_invoice_number(PDO $pdo): string
{
    $year = date('Y');
    $st = $pdo->prepare("SELECT number FROM invoices WHERE number LIKE ? ORDER BY id DESC LIMIT 1");
    $st->execute(['INV-' . $year . '-%']);
    $last = (string)$st->fetchColumn();
    $seq = $last ? ((int)substr($last, -4)) + 1 : 1;
    return sprintf('INV-%s-%04d', $year, $seq);
}

function page_header(string $title, string $active = ''): void
{
    $u = cuser();
    $brand = setting('brand_name', 'كونسول الاشتراكات');
    $nav = [
        ['index.php',    'لوحة التحكم',      '📊'],
        ['clinics.php',  'العيادات',          '🏥'],
        ['tenants.php',  'عيادات SaaS',       '🏢'],
        ['invoices.php', 'الفواتير',          '🧾'],
        ['payments.php', 'المدفوعات',         '💳'],
        ['plans.php',    'خطط الاشتراك',      '📦'],
        ['log.php',      'سجل النشاط',        '📜'],
        ['settings.php', 'الإعدادات',         '⚙️'],
    ];
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' | ' . e($brand) . '</title>';
    echo '<link rel="stylesheet" href="assets/console.css">';
    echo '</head><body><div class="layout">';

    echo '<aside class="sidebar"><div class="brand">💼 ' . e($brand) . '</div><nav>';
    foreach ($nav as [$href, $label, $icon]) {
        $cls = $active === $href ? ' class="active"' : '';
        echo '<a href="' . e($href) . '"' . $cls . '><span class="ico">' . $icon . '</span> ' . e($label) . '</a>';
    }
    echo '</nav></aside>';

    echo '<div class="main"><header class="topbar"><h1>' . e($title) . '</h1>';
    if ($u) {
        echo '<div class="userbox"><span><strong>' . e($u['name']) . '</strong></span>';
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
    echo '</main></div></div></body></html>';
}

/* ------------------------------------------------------------- الترقية */

function console_schema(): array
{
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS console_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $opts",
        "CREATE TABLE IF NOT EXISTS plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            months INT NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            features TEXT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1
        ) $opts",
        "CREATE TABLE IF NOT EXISTS clinics (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL,
            owner_name VARCHAR(150) NOT NULL DEFAULT '',
            phone VARCHAR(30) NOT NULL DEFAULT '',
            email VARCHAR(150) NOT NULL DEFAULT '',
            site_url VARCHAR(255) NOT NULL DEFAULT '',
            subdomain VARCHAR(40) NULL,
            custom_domain VARCHAR(120) NOT NULL DEFAULT '',
            db_name VARCHAR(64) NOT NULL DEFAULT '',
            plan_id INT UNSIGNED NULL,
            status ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'trial',
            start_date DATE NULL,
            expires_at DATE NULL,
            token VARCHAR(64) NOT NULL,
            last_ping_at DATETIME NULL,
            app_version VARCHAR(20) NOT NULL DEFAULT '',
            patients_count INT NOT NULL DEFAULT 0,
            users_count INT NOT NULL DEFAULT 0,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_token (token),
            UNIQUE KEY uq_subdomain (subdomain),
            INDEX idx_custom_domain (custom_domain),
            INDEX idx_status (status, expires_at)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS invoices (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            clinic_id INT UNSIGNED NOT NULL,
            number VARCHAR(30) NOT NULL,
            issue_date DATE NOT NULL,
            due_date DATE NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            paid DECIMAL(10,2) NOT NULL DEFAULT 0,
            months INT NOT NULL DEFAULT 1,
            plan_name VARCHAR(100) NOT NULL DEFAULT '',
            status ENUM('unpaid','partial','paid','void') NOT NULL DEFAULT 'unpaid',
            applied TINYINT(1) NOT NULL DEFAULT 0,
            pay_token VARCHAR(64) NOT NULL,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE,
            UNIQUE KEY uq_number (number),
            UNIQUE KEY uq_paytoken (pay_token),
            INDEX idx_status (status, due_date)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT UNSIGNED NULL,
            clinic_id INT UNSIGNED NOT NULL,
            pdate DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method ENUM('cash','instapay','paymob') NOT NULL DEFAULT 'cash',
            status ENUM('pending','confirmed','failed') NOT NULL DEFAULT 'confirmed',
            reference VARCHAR(120) NOT NULL DEFAULT '',
            gateway_order_id VARCHAR(60) NOT NULL DEFAULT '',
            gateway_txn_id VARCHAR(60) NOT NULL DEFAULT '',
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
            FOREIGN KEY (clinic_id) REFERENCES clinics(id) ON DELETE CASCADE,
            INDEX idx_date (pdate),
            INDEX idx_gw (gateway_order_id)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS console_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            action VARCHAR(30) NOT NULL,
            entity VARCHAR(30) NOT NULL,
            entity_id INT UNSIGNED NULL,
            summary VARCHAR(255) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(50) PRIMARY KEY,
            svalue TEXT NOT NULL
        ) $opts",
    ];
}

function console_migrate(PDO $pdo): void
{
    $has = $pdo->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
    if (!$has) {
        return;
    }
    $st = $pdo->query("SELECT svalue FROM settings WHERE skey = 'schema_version'");
    $current = (int)($st->fetchColumn() ?: 0);
    if ($current >= CONSOLE_SCHEMA_VERSION) {
        return;
    }

    if ($current < 2) {
        // أعمدة وضع SaaS: العيادة تُعرَف بنطاقها وتشير لقاعدتها الخاصة
        $addCol = function (string $table, string $col, string $def) use ($pdo): void {
            if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col))->fetchAll()) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        };
        // NULL لا NOT NULL: العيادات القائمة (تركيب مستقل) بلا نطاق فرعي، و MySQL
        // يسمح بتكرار NULL في الفهرس الفريد بينما يرفض تكرار السلسلة الفارغة
        $addCol('clinics', 'subdomain', 'VARCHAR(40) NULL');
        $addCol('clinics', 'custom_domain', "VARCHAR(120) NOT NULL DEFAULT ''");
        $addCol('clinics', 'db_name', "VARCHAR(64) NOT NULL DEFAULT ''");
        foreach ([
            "CREATE UNIQUE INDEX uq_subdomain ON clinics (subdomain)",
            "CREATE INDEX idx_custom_domain ON clinics (custom_domain)",
        ] as $idx) {
            try {
                $pdo->exec($idx);
            } catch (PDOException) {
                // الفهرس موجود بالفعل
            }
        }
        $defaults = [
            'base_domain'   => '',
            'base_scheme'   => 'https',
            'tenant_prefix' => 'clinic_',
            'trial_days'    => '14',
            'signup_open'   => '0',
        ];
        $set = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
        foreach ($defaults as $k => $v) {
            $set->execute([$k, $v]);
        }
    }

    $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                   ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
        ->execute(['schema_version', (string)CONSOLE_SCHEMA_VERSION]);
}
