<?php
declare(strict_types=1);

const ROLES = ['admin' => 'مدير', 'doctor' => 'أخصائي تغذية', 'reception' => 'استقبال'];

const APPT_TYPES  = ['new' => 'كشف جديد', 'followup' => 'متابعة', 'consult' => 'استشارة'];
const APPT_STATUS = ['scheduled' => 'محجوز', 'done' => 'تم', 'cancelled' => 'ملغي', 'no_show' => 'لم يحضر'];
const APPT_BADGE  = ['scheduled' => 'info', 'done' => 'ok', 'cancelled' => 'muted', 'no_show' => 'bad'];

const PAY_METHODS  = ['cash' => 'نقدي', 'card' => 'بطاقة', 'transfer' => 'تحويل بنكي', 'wallet' => 'محفظة إلكترونية'];
const EXPENSE_CATS = ['rent' => 'إيجار', 'salaries' => 'مرتبات', 'supplies' => 'مستلزمات', 'marketing' => 'تسويق', 'utilities' => 'مرافق وفواتير', 'other' => 'أخرى'];

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
        ['patients.php',     'المرضى',            '👥', ['admin', 'doctor', 'reception']],
        ['plans.php',        'الأنظمة الغذائية',  '🥗', ['admin', 'doctor', 'reception']],
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
