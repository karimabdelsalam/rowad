<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/inc/functions.php';
require_once __DIR__ . '/inc/schema.php';

$configFile = __DIR__ . '/inc/config.php';
$installed = false;
if (is_file($configFile)) {
    require $configFile;
    try {
        $chk = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $installed = (bool)$chk->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    } catch (PDOException) {
        $installed = false;
    }
}


$errors = [];
$done = false;
$configWritten = false;
$configContent = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    $dbHost = trim($_POST['db_host'] ?? 'localhost');
    $dbName = trim($_POST['db_name'] ?? '');
    $dbUser = trim($_POST['db_user'] ?? '');
    $dbPass = (string)($_POST['db_pass'] ?? '');
    $clinicName = trim($_POST['clinic_name'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if ($dbName === '' || $dbUser === '') $errors[] = 'أدخل اسم قاعدة البيانات واسم المستخدم.';
    if ($clinicName === '') $errors[] = 'أدخل اسم العيادة.';
    if ($adminName === '' || $adminUser === '') $errors[] = 'أدخل بيانات حساب المدير.';
    if (mb_strlen($adminPass) < 8) $errors[] = 'كلمة مرور المدير يجب ألا تقل عن 8 أحرف.';

    if (!$errors) {
        try {
            $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
        } catch (PDOException $e) {
            $errors[] = 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage();
        }
    }

    if (!$errors) {
        try {
            build_clinic_db($db, [
                'clinic_name'  => $clinicName,
                'admin_name'   => $adminName,
                'admin_user'   => $adminUser,
                'admin_pass'   => $adminPass,
                'country_code' => trim($_POST['country_code'] ?? '20') ?: '20',
            ]);

            $configContent = "<?php\n"
                . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                . "define('APP_TIMEZONE', 'Africa/Cairo');\n";
            $configWritten = @file_put_contents($configFile, $configContent) !== false;
            $done = true;
        } catch (PDOException $e) {
            $errors[] = 'خطأ أثناء إنشاء الجداول: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تثبيت نظام إدارة عيادة التغذية</title>
<link rel="stylesheet" href="assets/clinic.css">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:640px">
    <h1 class="auth-title">🍏 تثبيت نظام إدارة عيادة التغذية</h1>

    <?php if ($installed): ?>
        <div class="alert alert-success">النظام مثبت بالفعل ✔</div>
        <p>لأسباب أمنية <strong>احذف ملف <code>install.php</code></strong> من السيرفر.</p>
        <a class="btn" href="login.php">الذهاب لتسجيل الدخول</a>

    <?php elseif ($done): ?>
        <div class="alert alert-success">تم التثبيت بنجاح 🎉</div>
        <?php if (!$configWritten): ?>
            <div class="alert alert-warning">تعذر إنشاء ملف الإعدادات تلقائيًا. أنشئ ملف
            <code>inc/config.php</code> يدويًا بهذا المحتوى:</div>
            <pre dir="ltr" style="background:#0f172a;color:#e2e8f0;padding:12px;border-radius:8px;overflow:auto"><?= htmlspecialchars($configContent) ?></pre>
        <?php endif; ?>
        <p><strong>مهم:</strong> احذف ملف <code>install.php</code> من السيرفر الآن.</p>
        <p>بعد تسجيل الدخول هيستقبلك <strong>معالج التهيئة</strong> ويمشي معك خطوة بخطوة
            لضبط بيانات العيادة والأسعار وفريق العمل.</p>
        <a class="btn" href="login.php">الذهاب لتسجيل الدخول</a>

    <?php else: ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
        <form method="post">
            <h3 class="form-section">بيانات قاعدة البيانات (من cPanel → MySQL Databases)</h3>
            <div class="grid2">
                <label>السيرفر <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required></label>
                <label>اسم قاعدة البيانات <input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required dir="ltr"></label>
                <label>اسم المستخدم <input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required dir="ltr"></label>
                <label>كلمة المرور <input type="password" name="db_pass" dir="ltr"></label>
            </div>
            <h3 class="form-section">بيانات العيادة</h3>
            <label>اسم العيادة <input name="clinic_name" value="<?= htmlspecialchars($_POST['clinic_name'] ?? '') ?>" required placeholder="مثال: عيادة د. أحمد للتغذية العلاجية"></label>
            <label>كود الدولة لأرقام واتساب (مصر 20، السعودية 966، الإمارات 971)
                <input name="country_code" value="<?= htmlspecialchars($_POST['country_code'] ?? '20') ?>" dir="ltr"></label>
            <h3 class="form-section">حساب المدير</h3>
            <div class="grid2">
                <label>الاسم <input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required></label>
                <label>اسم الدخول <input name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? '') ?>" required dir="ltr"></label>
                <label>كلمة المرور (8 أحرف على الأقل) <input type="password" name="admin_pass" required minlength="8" dir="ltr"></label>
            </div>
            <button class="btn btn-block" type="submit">تثبيت النظام</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
