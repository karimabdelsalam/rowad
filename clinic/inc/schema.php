<?php
declare(strict_types=1);

/**
 * شكل قاعدة بيانات العيادة: السكيما والترقيات والبذور الأولية.
 *
 * وحدة مستقلة تمامًا عن الواجهة والجلسة، فلا تعرّف أي دالة عرض أو مساعد عام.
 * سبب الاستقلال أن كونسول الاشتراكات يحمّلها ليجهّز قواعد العيادات الجديدة
 * ويرقّيها، ولو حمّل معها دوال العيادة لتصادمت أسماؤها مع دوال الكونسول.
 */

require_once __DIR__ . '/diet_library.php';

function wa_default_template(): string
{
    return "مرحبًا {الاسم} 🌿\n"
        . "نذكّركم بموعدكم في {العيادة} يوم {اليوم} الموافق {التاريخ} الساعة {الوقت}.\n"
        . "برجاء الحضور قبل الموعد بـ 10 دقائق.\n"
        . "لتأكيد أو تعديل الموعد: {هاتف_العيادة}";
}

const SCHEMA_VERSION = 11;

/**
 * وحدات النظام القابلة للتشغيل والإيقاف.
 *
 * العيادة تشغّل ما تستخدمه فقط: عيادة لا تتعامل بالحقن تُطفئ وحدة الحقن فتختفي
 * من القائمة وتُرفض صفحاتها. المفتاح يُحفظ في الإعدادات باسم mod_<الوحدة>.
 */
const CLINIC_MODULES = [
    'plans'      => ['الأنظمة الغذائية', '🥗', 'إنشاء أنظمة غذائية للمرضى ومكتبة البرامج الجاهزة'],
    'injections' => ['الحقن والأدوية', '💉', 'حقن التخسيس والمحاسبة بالوحدات ومخزون الأدوية'],
    'packages'   => ['باقات الجلسات', '🎟️', 'بيع باقات جلسات وخصمها ومتابعة المتبقي'],
    'queue'      => ['الدور والانتظار', '🔢', 'أرقام الدور وشاشة صالة الانتظار'],
];

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

/** جداول سجل النشاط والدور والمرفقات */
function ops_schema(): array
{
    $opts = 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    return [
        "CREATE TABLE IF NOT EXISTS payment_requests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description VARCHAR(150) NOT NULL DEFAULT '',
            token VARCHAR(64) NOT NULL,
            status ENUM('pending','paid','cancelled','failed') NOT NULL DEFAULT 'pending',
            method ENUM('paymob','instapay','cash') NULL,
            gateway_order_id VARCHAR(60) NOT NULL DEFAULT '',
            gateway_txn_id VARCHAR(60) NOT NULL DEFAULT '',
            payment_id INT UNSIGNED NULL,
            expires_at DATETIME NULL,
            paid_at DATETIME NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            UNIQUE KEY uq_token (token),
            INDEX idx_status (status, created_at),
            INDEX idx_gw (gateway_order_id)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS activity_log (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NULL,
            user_name VARCHAR(100) NOT NULL DEFAULT '',
            action VARCHAR(30) NOT NULL,
            entity VARCHAR(30) NOT NULL DEFAULT '',
            entity_id INT UNSIGNED NULL,
            summary VARCHAR(255) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_created (created_at),
            INDEX idx_user (user_id, created_at),
            INDEX idx_entity (entity, entity_id)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS queue (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            qdate DATE NOT NULL,
            number INT UNSIGNED NOT NULL,
            patient_id INT UNSIGNED NOT NULL,
            doctor_id INT UNSIGNED NULL,
            appointment_id INT UNSIGNED NULL,
            status ENUM('waiting','in_room','done','skipped') NOT NULL DEFAULT 'waiting',
            arrived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            called_at DATETIME NULL,
            done_at DATETIME NULL,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            created_by INT UNSIGNED NULL,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            UNIQUE KEY uq_day_number (qdate, number),
            INDEX idx_day (qdate, status)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS attachments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            stored_name VARCHAR(80) NOT NULL,
            original_name VARCHAR(180) NOT NULL DEFAULT '',
            mime VARCHAR(100) NOT NULL DEFAULT '',
            size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            category ENUM('lab','photo','report','other') NOT NULL DEFAULT 'other',
            taken_date DATE NULL,
            notes VARCHAR(255) NOT NULL DEFAULT '',
            uploaded_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_patient (patient_id, category, taken_date)
        ) $opts",
    ];
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
    /*
     * الإصدار يُقرأ من القاعدة الممرَّرة لا من الإعدادات العامة: الترقية تعمل
     * على قواعد عيادات أخرى غير القاعدة الحالية عند تحديث كل العيادات دفعة
     * واحدة، فقراءته من الكاش العام كانت سترقّي القاعدة الخطأ.
     */
    try {
        $st = $pdo->query("SELECT svalue FROM settings WHERE skey = 'schema_version'");
        $current = (int)($st->fetchColumn() ?: 1);
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

    if ($current < 5) {
        if (!$pdo->query("SHOW COLUMNS FROM users LIKE 'perms'")->fetchAll()) {
            $pdo->exec('ALTER TABLE users ADD COLUMN perms TEXT NULL');
        }
    }

    if ($current < 6) {
        // فهارس الأعمدة التي تُستخدم في الفلترة على كل صفحة تقريبًا
        $addIndex = function (string $table, string $name, string $cols) use ($pdo): void {
            $exists = $pdo->query("SHOW INDEX FROM `$table` WHERE Key_name = " . $pdo->quote($name))->fetchAll();
            if (!$exists) {
                $pdo->exec("ALTER TABLE `$table` ADD INDEX `$name` ($cols)");
            }
        };
        $addIndex('patients', 'idx_doctor', 'doctor_id');
        $addIndex('appointments', 'idx_doctor', 'doctor_id, adate');
        $addIndex('injection_doses', 'idx_patient_date', 'patient_id, dose_date');
    }

    if ($current < 7) {
        foreach (ops_schema() as $sql) {
            $pdo->exec($sql);
        }
        $st = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
        $st->execute(['max_upload_mb', '8']);
        $st->execute(['inactive_days', '45']);
    }

    if ($current < 8) {
        $addCol = function (string $table, string $col, string $def) use ($pdo): void {
            if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col))->fetchAll()) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        };
        $addCol('appointments', 'confirm_status',
            "ENUM('pending','confirmed','no_answer','declined') NOT NULL DEFAULT 'pending'");
        $addCol('appointments', 'confirmed_at', 'DATETIME NULL');
        $addCol('appointments', 'confirmed_by', 'INT UNSIGNED NULL');
    }

    if ($current < 9) {
        $addCol = function (string $table, string $col, string $def) use ($pdo): void {
            if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE " . $pdo->quote($col))->fetchAll()) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
            }
        };
        $addCol('diet_templates', 'category', "ENUM('weight','therapeutic','sports','general') NOT NULL DEFAULT 'weight'");
        $addCol('diet_templates', 'tag', "VARCHAR(30) NOT NULL DEFAULT ''");
        $addCol('diet_templates', 'description', "VARCHAR(255) NOT NULL DEFAULT ''");
        $addCol('diet_templates', 'warnings', 'TEXT NULL');
        $addCol('diet_templates', 'forbidden', 'TEXT NULL');
        $addCol('diet_plans', 'warnings', 'TEXT NULL');
        seed_diet_library($pdo);
    }

    if ($current < 11) {
        // طلبات الدفع الأونلاين للمرضى — تُنشأ هنا للتركيبات القائمة
        foreach (ops_schema() as $sql) {
            if (str_contains($sql, 'payment_requests')) {
                $pdo->exec($sql);
            }
        }
    }

    if ($current < 10) {
        // تركيب قائم بالفعل: كل الوحدات مفعّلة، ولا يُعرض عليه معالج التهيئة
        $set = $pdo->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
        foreach (array_keys(CLINIC_MODULES) as $m) {
            $set->execute(['mod_' . $m, '1']);
        }
        $set->execute(['setup_done', '1']);
    }

    $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
        ->execute(['schema_version', (string)SCHEMA_VERSION]);
    // الكاش يخص القاعدة الحالية فقط؛ عند ترقية قاعدة عيادة أخرى لا كاش يُمسح
    if (function_exists('setting_flush')) {
        setting_flush();
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
            perms TEXT NULL,
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
            confirm_status ENUM('pending','confirmed','no_answer','declined') NOT NULL DEFAULT 'pending',
            confirmed_at DATETIME NULL,
            confirmed_by INT UNSIGNED NULL,
            created_by INT UNSIGNED NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
            INDEX idx_date (adate, atime)
        ) $opts",
        "CREATE TABLE IF NOT EXISTS diet_templates (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(150) NOT NULL,
            category ENUM('weight','therapeutic','sports','general') NOT NULL DEFAULT 'weight',
            tag VARCHAR(30) NOT NULL DEFAULT '',
            description VARCHAR(255) NOT NULL DEFAULT '',
            calories INT NULL,
            breakfast TEXT NULL,
            snack1 TEXT NULL,
            lunch TEXT NULL,
            snack2 TEXT NULL,
            dinner TEXT NULL,
            forbidden TEXT NULL,
            notes TEXT NULL,
            warnings TEXT NULL
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
            warnings TEXT NULL,
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
        ...packages_schema(),
        ...ops_schema(),
    ];
}

/**
 * ينشئ جداول عيادة جديدة ويزرع بياناتها الأولية داخل قاعدة جاهزة.
 *
 * تُستدعى من المثبّت اليدوي ومن التوفير التلقائي على السواء، فتخرج كل عيادة
 * بنفس البنية ونفس المحتوى المبدئي.
 *
 * @param array{clinic_name:string, admin_name:string, admin_user:string,
 *              admin_pass:string, country_code?:string} $opt
 */
function build_clinic_db(PDO $db, array $opt): void
{
    foreach (schema_statements() as $sql) {
        $db->exec($sql);
    }

    $addCol = function (string $table, string $col, string $def) use ($db): void {
        if (!$db->query("SHOW COLUMNS FROM `$table` LIKE " . $db->quote($col))->fetchAll()) {
            $db->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        }
    };
    $addCol('patients', 'doctor_id', 'INT UNSIGNED NULL');
    $addCol('patients', 'portal_password', 'VARCHAR(255) NULL');
    $addCol('patients', 'portal_enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
    $addCol('appointments', 'doctor_id', 'INT UNSIGNED NULL');
    $addCol('appointments', 'patient_package_id', 'INT UNSIGNED NULL');

    if (!$db->query("SHOW COLUMNS FROM payments LIKE 'dose_id'")->fetchAll()) {
        $db->exec('ALTER TABLE payments ADD COLUMN dose_id INT UNSIGNED NULL');
        $db->exec('ALTER TABLE payments ADD CONSTRAINT fk_pay_dose FOREIGN KEY (dose_id)
                   REFERENCES injection_doses(id) ON DELETE CASCADE');
    }

    if (!(int)$db->query('SELECT COUNT(*) FROM packages')->fetchColumn()) {
        $pk = $db->prepare('INSERT INTO packages (name, sessions, price, validity_days, includes) VALUES (?,?,?,?,?)');
        $pk->execute(['باقة 4 جلسات متابعة', 4, 500, 60, 'أربع جلسات متابعة مع قياسات وتعديل النظام الغذائي']);
        $pk->execute(['باقة 8 جلسات متابعة', 8, 900, 120, 'ثماني جلسات متابعة — أوفر للمتابعة الشهرية']);
        $pk->execute(['باقة 12 جلسة (3 شهور)', 12, 1200, 180, 'متابعة كاملة لمدة ثلاثة شهور']);
    }

    if (!(int)$db->query('SELECT COUNT(*) FROM drugs')->fetchColumn()) {
        $drug = $db->prepare('INSERT INTO drugs (name, units_per_pen, unit_price, cost_per_pen, low_units, notes) VALUES (?,?,?,?,?,?)');
        $drug->execute(['ساكسيندا Saxenda 6mg/ml (قلم 3 مل)', 300, 0, 0, 100, 'ليراجلوتايد — جرعة يومية عادةً']);
        $drug->execute(['أوزمبك Ozempic (قلم 3 مل)', 300, 0, 0, 100, 'سيماجلوتايد — جرعة أسبوعية']);
        $drug->execute(['مونجارو Mounjaro (قلم)', 300, 0, 0, 100, 'تيرزيباتايد — جرعة أسبوعية']);
    }

    if (!(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) {
        $db->prepare('INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, "admin")')
           ->execute([
               $opt['admin_name'],
               $opt['admin_user'],
               password_hash($opt['admin_pass'], PASSWORD_DEFAULT),
           ]);
    }

    $defaults = [
        'clinic_name'      => $opt['clinic_name'],
        'clinic_phone'     => '',
        'clinic_address'   => '',
        'currency'         => 'ج.م',
        'price_new'        => '300',
        'price_followup'   => '150',
        'print_note'       => 'نتمنى لكم دوام الصحة والعافية 🌿',
        'country_code'     => $opt['country_code'] ?? '20',
        'wa_template'      => wa_default_template(),
        'schema_version'   => (string)SCHEMA_VERSION,
        'doctor_scope'     => 'own',
        'notify_enabled'   => '0',
        'notify_channel'   => 'whatsapp',
        'notify_provider'  => 'webhook',
        'notify_lead_days' => '1',
        'cron_token'       => bin2hex(random_bytes(16)),
        'portal_enabled'   => '1',
        'max_upload_mb'    => '8',
        'inactive_days'    => '45',
        'setup_done'       => '0',
    ];
    foreach (array_keys(CLINIC_MODULES) as $mod) {
        $defaults['mod_' . $mod] = '1';
    }
    $st = $db->prepare('INSERT IGNORE INTO settings (skey, svalue) VALUES (?, ?)');
    foreach ($defaults as $k => $v) {
        $st->execute([$k, $v]);
    }

    seed_diet_library($db);
}
