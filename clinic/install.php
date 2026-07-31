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
        $installed = (bool)$chk->query("SHOW TABLES LIKE 'users'")->fetchColumn();
    } catch (PDOException) {
        $installed = false;
    }
}

function schema_statements(): array
{
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','doctor','reception') NOT NULL DEFAULT 'reception',
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) $opts",
        "CREATE TABLE IF NOT EXISTS patients (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL DEFAULT '',
            name VARCHAR(150) NOT NULL,
            phone VARCHAR(30) NOT NULL DEFAULT '',
            gender ENUM('male','female') NOT NULL DEFAULT 'female',
            birth_date DATE NULL,
            height_cm DECIMAL(5,1) NULL,
            job VARCHAR(100) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            medical_conditions TEXT NULL,
            allergies TEXT NULL,
            goal VARCHAR(255) NOT NULL DEFAULT '',
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name),
            INDEX idx_phone (phone)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS measurements (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            mdate DATE NOT NULL,
            weight DECIMAL(5,1) NOT NULL,
            body_fat DECIMAL(4,1) NULL,
            muscle DECIMAL(5,1) NULL,
            water DECIMAL(4,1) NULL,
            waist DECIMAL(5,1) NULL,
            hips DECIMAL(5,1) NULL,
            arm DECIMAL(4,1) NULL,
            thigh DECIMAL(4,1) NULL,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_pd (patient_id, mdate)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS appointments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            adate DATE NOT NULL,
            atime TIME NOT NULL,
            type ENUM('new','followup','consult') NOT NULL DEFAULT 'followup',
            status ENUM('scheduled','done','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
            notes VARCHAR(255) NOT NULL DEFAULT '',
            reminder_sent DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_date (adate, atime)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS diet_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            calories INT NULL,
            breakfast TEXT NULL,
            snack1 TEXT NULL,
            lunch TEXT NULL,
            snack2 TEXT NULL,
            dinner TEXT NULL,
            notes TEXT NULL
        ) $opts",
        "CREATE TABLE IF NOT EXISTS diet_plans (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            title VARCHAR(150) NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE NULL,
            calories INT NULL,
            breakfast TEXT NULL,
            snack1 TEXT NULL,
            lunch TEXT NULL,
            snack2 TEXT NULL,
            dinner TEXT NULL,
            forbidden TEXT NULL,
            notes TEXT NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_p (patient_id, start_date)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS payments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NULL,
            pdate DATE NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            method ENUM('cash','card','transfer','wallet') NOT NULL DEFAULT 'cash',
            service VARCHAR(100) NOT NULL DEFAULT '',
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
            INDEX idx_date (pdate)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS expenses (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            edate DATE NOT NULL,
            category ENUM('rent','salaries','supplies','marketing','utilities','other') NOT NULL DEFAULT 'other',
            amount DECIMAL(10,2) NOT NULL,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            INDEX idx_date (edate)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS settings (
            skey VARCHAR(50) PRIMARY KEY,
            svalue TEXT NOT NULL
        ) $opts",
        ...injection_schema(),
    ];
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
            foreach (schema_statements() as $sql) {
                $db->exec($sql);
            }

            // ربط المدفوعات بجرعات الحقن (يُنفَّذ مرة واحدة فقط)
            if (!$db->query("SHOW COLUMNS FROM payments LIKE 'dose_id'")->fetchAll()) {
                $db->exec('ALTER TABLE payments ADD COLUMN dose_id INT UNSIGNED NULL');
                $db->exec('ALTER TABLE payments ADD CONSTRAINT fk_pay_dose FOREIGN KEY (dose_id)
                           REFERENCES injection_doses(id) ON DELETE CASCADE');
            }

            // أدوية شائعة كبداية — قابلة للتعديل والحذف من صفحة الأدوية
            if (!(int)$db->query('SELECT COUNT(*) FROM drugs')->fetchColumn()) {
                $drug = $db->prepare('INSERT INTO drugs (name, units_per_pen, unit_price, cost_per_pen, low_units, notes) VALUES (?,?,?,?,?,?)');
                $drug->execute(['ساكسيندا Saxenda 6mg/ml (قلم 3 مل)', 300, 0, 0, 100, 'ليراجلوتايد — جرعة يومية عادةً']);
                $drug->execute(['أوزمبك Ozempic (قلم 3 مل)', 300, 0, 0, 100, 'سيماجلوتايد — جرعة أسبوعية']);
                $drug->execute(['مونجارو Mounjaro (قلم)', 300, 0, 0, 100, 'تيرزيباتايد — جرعة أسبوعية']);
            }

            $st = $db->prepare('INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, "admin")');
            $st->execute([$adminName, $adminUser, password_hash($adminPass, PASSWORD_DEFAULT)]);

            $defaults = [
                'clinic_name'    => $clinicName,
                'clinic_phone'   => '',
                'clinic_address' => '',
                'currency'       => 'ج.م',
                'price_new'      => '300',
                'price_followup' => '150',
                'print_note'     => 'نتمنى لكم دوام الصحة والعافية 🌿',
                'country_code'   => trim($_POST['country_code'] ?? '20') ?: '20',
                'wa_template'    => wa_default_template(),
                'schema_version' => (string)SCHEMA_VERSION,
            ];
            $st = $db->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
            foreach ($defaults as $k => $v) {
                $st->execute([$k, $v]);
            }

            if (!(int)$db->query('SELECT COUNT(*) FROM diet_templates')->fetchColumn()) {
                $tpl = $db->prepare('INSERT INTO diet_templates (title, calories, breakfast, snack1, lunch, snack2, dinner, notes) VALUES (?,?,?,?,?,?,?,?)');
                $tpl->execute([
                    'نظام 1200 سعر حراري', 1200,
                    "2 بيضة مسلوقة + ربع رغيف بلدي + خيار وطماطم\nأو: 3 ملاعق فول بليمون وكمون + ربع رغيف",
                    "ثمرة فاكهة (تفاح / برتقال / جوافة)",
                    "ربع فرخة مشوية بدون جلد أو سمكة مشوية\n+ 3 ملاعق أرز أو ربع رغيف\n+ طبق سلطة خضراء كبير + خضار سوتيه",
                    "كوب زبادي لايت أو حفنة مكسرات نيئة (5-7 حبات)",
                    "علبة تونة مصفاة أو قطعة جبن قريش\n+ طبق سلطة خضراء",
                    "الماء: 2-3 لتر يوميًا. المشي: 30 دقيقة يوميًا.\nممنوع: السكر الأبيض، المقليات، المشروبات الغازية.",
                ]);
                $tpl->execute([
                    'نظام 1500 سعر حراري', 1500,
                    "2 بيضة أومليت بقليل من الزيت + نصف رغيف بلدي + جبن قريش + خضروات",
                    "ثمرة فاكهة + 3 تمرات أو كوب عصير طبيعي بدون سكر",
                    "صدر فرخة مشوي أو لحم مسلوق (150 جم)\n+ 5 ملاعق أرز أو مكرونة مسلوقة\n+ سلطة خضراء + شوربة خضار",
                    "كوب زبادي + ملعقة شوفان",
                    "2 توست سن + جبن قريش أو بيضة مسلوقة + سلطة",
                    "الماء: 3 لتر يوميًا. رياضة: 45 دقيقة 3 مرات أسبوعيًا.",
                ]);
            }

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
