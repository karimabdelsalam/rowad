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

require __DIR__ . '/functions.php';
require_once __DIR__ . '/tenant.php';

if (saas_mode()) {
    /*
     * وضع SaaS: العيادة تُحدَّد من اسم النطاق، ولكل عيادة قاعدتها. العزل يقع
     * هنا عند الاتصال، فلا يوجد استعلام لاحق يمكن أن يُنسى فيه فلتر العيادة.
     */
    try {
        $tenantRow = resolve_tenant();
    } catch (PDOException) {
        http_response_code(503);
        exit('تعذر الوصول لقاعدة التحكم — حاول بعد قليل.');
    }

    if (!$tenantRow) {
        http_response_code(404);
        exit('لا توجد عيادة على هذا العنوان.');
    }
    tenant($tenantRow);

    // جلسة باسم العيادة، فلا تتسرب جلسة عيادة لأخرى على نفس النطاق الأساسي
    session_name('clinic_' . preg_replace('/[^a-z0-9]/', '', (string)$tenantRow['subdomain']));
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

    try {
        $pdo = tenant_pdo((string)$tenantRow['db_name']);
    } catch (PDOException) {
        http_response_code(503);
        exit('تعذر الاتصال بقاعدة بيانات العيادة.');
    }
} else {
    session_name('clinic_session');
    session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax']);

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
    db_sync_timezone($pdo);
}

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

db_migrate($pdo);

// يمنع الكتابة بعد انتهاء مهلة الاشتراك؛ القراءة والتصدير يبقيان مفتوحين
tenant_guard_write();

/*
 * تحديث بيانات المستخدم من قاعدة البيانات في كل طلب، حتى يسري أي تغيير في
 * الصلاحيات أو الدور فورًا، ويُطرد المستخدم المعطَّل بدل انتظار انتهاء جلسته.
 */
if (!empty($_SESSION['user']['id'])) {
    $st = $pdo->prepare('SELECT id, name, role, perms, active FROM users WHERE id = ?');
    $st->execute([(int)$_SESSION['user']['id']]);
    $fresh = $st->fetch();
    if (!$fresh || !$fresh['active']) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php');
    }
    $_SESSION['user'] = [
        'id'    => (int)$fresh['id'],
        'name'  => $fresh['name'],
        'role'  => $fresh['role'],
        'perms' => $fresh['perms'],
    ];

    // فحص الاشتراك مرة يوميًا للنسخ المستقلة المرتبطة بكونسول مزوّد
    if (!saas_mode()) {
        license_refresh($pdo);
    }
}
