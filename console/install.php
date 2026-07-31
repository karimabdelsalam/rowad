<?php
declare(strict_types=1);
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/inc/functions.php';

$configFile = __DIR__ . '/inc/config.php';
$installed = false;
if (is_file($configFile)) {
    require $configFile;
    try {
        $chk = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
        $installed = (bool)$chk->query("SHOW TABLES LIKE 'console_users'")->fetchColumn();
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
    $brand = trim($_POST['brand_name'] ?? '');
    $adminName = trim($_POST['admin_name'] ?? '');
    $adminUser = trim($_POST['admin_user'] ?? '');
    $adminPass = (string)($_POST['admin_pass'] ?? '');

    if ($dbName === '' || $dbUser === '') $errors[] = 'أدخل بيانات قاعدة البيانات.';
    if ($brand === '') $errors[] = 'أدخل اسم شركتك أو نشاطك.';
    if ($adminName === '' || $adminUser === '') $errors[] = 'أدخل بيانات حسابك.';
    if (mb_strlen($adminPass) < 8) $errors[] = 'كلمة المرور يجب ألا تقل عن 8 أحرف.';

    if (!$errors) {
        try {
            $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        } catch (PDOException $ex) {
            $errors[] = 'فشل الاتصال بقاعدة البيانات: ' . $ex->getMessage();
        }
    }

    if (!$errors) {
        try {
            foreach (console_schema() as $sql) {
                $db->exec($sql);
            }
            $db->prepare('INSERT INTO console_users (name, username, password) VALUES (?,?,?)')
               ->execute([$adminName, $adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);

            if (!(int)$db->query('SELECT COUNT(*) FROM plans')->fetchColumn()) {
                $pl = $db->prepare('INSERT INTO plans (name, months, price, features) VALUES (?,?,?,?)');
                $pl->execute(['شهري', 1, 500, "النظام كامل\nتحديثات مجانية\nدعم فني"]);
                $pl->execute(['ربع سنوي', 3, 1350, "النظام كامل\nتحديثات مجانية\nدعم فني\nخصم 10%"]);
                $pl->execute(['سنوي', 12, 4800, "النظام كامل\nتحديثات مجانية\nدعم فني بالأولوية\nخصم 20%"]);
            }

            $defaults = [
                'brand_name'     => $brand,
                'currency'       => 'ج.م',
                'schema_version' => (string)CONSOLE_SCHEMA_VERSION,
                'instapay_addr'  => '',
                'instapay_note'  => 'حوّل المبلغ على العنوان الموضح، ثم اكتب رقم العملية ليتم تأكيد اشتراكك.',
                'paymob_currency' => 'EGP',
                'grace_days'     => '7',
            ];
            $st = $db->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
            foreach ($defaults as $k => $v) {
                $st->execute([$k, $v]);
            }

            $configContent = "<?php\n"
                . "define('DB_HOST', " . var_export($dbHost, true) . ");\n"
                . "define('DB_NAME', " . var_export($dbName, true) . ");\n"
                . "define('DB_USER', " . var_export($dbUser, true) . ");\n"
                . "define('DB_PASS', " . var_export($dbPass, true) . ");\n"
                . "define('APP_TIMEZONE', 'Africa/Cairo');\n";
            $configWritten = @file_put_contents($configFile, $configContent) !== false;
            $done = true;
        } catch (PDOException $ex) {
            $errors[] = 'خطأ أثناء إنشاء الجداول: ' . $ex->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تثبيت كونسول الاشتراكات</title>
<link rel="stylesheet" href="assets/console.css">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:640px">
    <h1 class="auth-title">💼 تثبيت كونسول الاشتراكات</h1>

    <?php if ($installed): ?>
        <div class="alert alert-success">الكونسول مثبت بالفعل ✔</div>
        <p>لأسباب أمنية <strong>احذف ملف <code>install.php</code></strong> من السيرفر.</p>
        <a class="btn" href="login.php">تسجيل الدخول</a>

    <?php elseif ($done): ?>
        <div class="alert alert-success">تم التثبيت بنجاح 🎉</div>
        <?php if (!$configWritten): ?>
            <div class="alert alert-warning">تعذر إنشاء ملف الإعدادات تلقائيًا. أنشئ
            <code>inc/config.php</code> بهذا المحتوى:</div>
            <pre dir="ltr"><?= htmlspecialchars($configContent) ?></pre>
        <?php endif; ?>
        <p><strong>مهم:</strong> احذف ملف <code>install.php</code> من السيرفر الآن.</p>
        <a class="btn" href="login.php">تسجيل الدخول</a>

    <?php else: ?>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
        <div class="alert alert-warning">
            استخدم <strong>قاعدة بيانات منفصلة</strong> عن قواعد بيانات العيادات — هذه بياناتك التجارية.
        </div>
        <form method="post">
            <h3 class="form-section">قاعدة البيانات (من cPanel → MySQL Databases)</h3>
            <div class="grid2">
                <label>السيرفر <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>" required></label>
                <label>اسم القاعدة <input name="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required dir="ltr"></label>
                <label>المستخدم <input name="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required dir="ltr"></label>
                <label>كلمة المرور <input type="password" name="db_pass" dir="ltr"></label>
            </div>
            <h3 class="form-section">نشاطك</h3>
            <label>اسم الشركة أو النشاط
                <input name="brand_name" value="<?= htmlspecialchars($_POST['brand_name'] ?? '') ?>" required
                       placeholder="مثال: رواد لأنظمة العيادات"></label>
            <h3 class="form-section">حسابك</h3>
            <div class="grid2">
                <label>الاسم <input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? '') ?>" required></label>
                <label>اسم الدخول <input name="admin_user" value="<?= htmlspecialchars($_POST['admin_user'] ?? '') ?>" required dir="ltr"></label>
                <label>كلمة المرور (8 أحرف على الأقل) <input type="password" name="admin_pass" required minlength="8" dir="ltr"></label>
            </div>
            <button class="btn btn-block" type="submit">تثبيت الكونسول</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
