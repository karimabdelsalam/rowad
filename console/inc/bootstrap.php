<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');

$configFile = __DIR__ . '/config.php';
if (!is_file($configFile)) {
    header('Location: install.php');
    exit;
}
require $configFile;

date_default_timezone_set(defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Africa/Cairo');

// جلسة مستقلة عن نظام العيادة حتى لا يمنح دخولٌ في أحدهما دخولًا في الآخر
session_name('console_session');
session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
]);

require __DIR__ . '/functions.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException) {
    http_response_code(500);
    exit('تعذر الاتصال بقاعدة البيانات — راجع إعدادات ملف inc/config.php');
}

/*
 * توحيد ساعة MySQL مع ساعة PHP: بدونه يكتب NOW() بتوقيت السيرفر بينما يفلتر
 * PHP بتوقيت التطبيق، فتختفي سجلات «اليوم» من تقارير «اليوم».
 */
try {
    $tzOffset = (new DateTime('now', new DateTimeZone(date_default_timezone_get())))->format('P');
    $pdo->exec("SET time_zone = '$tzOffset'");
} catch (PDOException) {
    // بعض الاستضافات تمنع تغيير المنطقة الزمنية للجلسة
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

console_migrate($pdo);

// إعادة قراءة المستخدم كل طلب، فيُطرد الحساب المعطَّل فورًا
if (!empty($_SESSION['cuser']['id'])) {
    $st = $pdo->prepare('SELECT id, name, active FROM console_users WHERE id = ?');
    $st->execute([(int)$_SESSION['cuser']['id']]);
    $fresh = $st->fetch();
    if (!$fresh || !$fresh['active']) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php');
    }
    $_SESSION['cuser'] = ['id' => (int)$fresh['id'], 'name' => $fresh['name']];
}
