<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');

$root = dirname(__DIR__, 2);
$configFile = $root . '/inc/config.php';
if (!is_file($configFile)) {
    exit('النظام غير مثبت بعد.');
}
require $configFile;
require $root . '/inc/functions.php';

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Cairo');

// جلسة منفصلة تمامًا عن جلسة الموظفين
session_name('clinic_portal');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException) {
    http_response_code(500);
    exit('تعذر الاتصال بقاعدة البيانات.');
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

if (setting('portal_enabled', '1') !== '1') {
    exit('بوابة المرضى غير مفعّلة حاليًا. برجاء التواصل مع العيادة.');
}

/** المريض المسجَّل دخوله في البوابة */
function portal_patient(): ?array
{
    return $_SESSION['patient'] ?? null;
}

function portal_require_login(): void
{
    if (!portal_patient()) {
        header('Location: login.php');
        exit;
    }
}

/** بيانات المريض المحدَّثة من قاعدة البيانات */
function portal_load(PDO $pdo): array
{
    $st = $pdo->prepare('SELECT * FROM patients WHERE id = ? AND portal_enabled = 1');
    $st->execute([(int)portal_patient()['id']]);
    $row = $st->fetch();
    if (!$row) {
        session_destroy();
        header('Location: login.php');
        exit;
    }
    return $row;
}

function portal_header(string $title, string $active = ''): void
{
    $clinic = setting('clinic_name', 'العيادة');
    $nav = [
        ['index.php',    'الرئيسية',      '🏠'],
        ['progress.php', 'وزني',          '📉'],
        ['plan.php',     'نظامي الغذائي', '🥗'],
        ['account.php',  'حسابي',         '💳'],
    ];
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">';
    echo '<meta name="theme-color" content="#0f766e">';
    echo '<title>' . e($title) . ' | ' . e($clinic) . '</title>';
    echo '<link rel="stylesheet" href="assets/portal.css">';
    echo '<link rel="manifest" href="manifest.php">';
    echo '</head><body>';
    echo '<header class="pheader"><div class="pbrand">🍏 ' . e($clinic) . '</div>';
    echo '<a class="plogout" href="logout.php">خروج</a></header>';
    echo '<main class="pmain">';
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<div class="palert palert-' . e($f['t']) . '">' . e($f['m']) . '</div>';
    }
    unset($_SESSION['flash']);
    echo '<h1 class="ptitle">' . e($title) . '</h1>';

    // شريط التنقل السفلي يُطبع في التذييل، لكن نحفظ الحالة هنا
    $GLOBALS['portal_nav'] = [$nav, $active];
}

function portal_footer(): void
{
    [$nav, $active] = $GLOBALS['portal_nav'] ?? [[], ''];
    echo '</main><nav class="ptabs">';
    foreach ($nav as [$href, $label, $icon]) {
        $cls = $active === $href ? ' class="on"' : '';
        echo '<a href="' . e($href) . '"' . $cls . '><span>' . $icon . '</span>' . e($label) . '</a>';
    }
    echo '</nav></body></html>';
}
