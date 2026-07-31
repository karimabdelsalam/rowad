<?php
/*
 * إعدادات النظام — انسخ هذا الملف باسم config.php.
 *
 * للنظام وضعان، اختر واحدًا:
 *   (أ) عيادة واحدة  — نسخة مستقلة على استضافة العميل (الافتراضي).
 *   (ب) SaaS         — نظام واحد يخدم عيادات كثيرة، لكل عيادة قاعدتها ونطاقها.
 */

/* ================================ (أ) عيادة واحدة ================================ */

define('DB_HOST', 'localhost');
define('DB_NAME', 'clinic');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_TIMEZONE', 'Africa/Cairo');

/*
 * ربط اختياري بكونسول اشتراكات (للنسخ المُباعة باشتراك لا برخصة دائمة).
 * انسخ السطرين من ملف العيادة داخل الكونسول. النظام لا يُقفل عند الانتهاء،
 * وإنما يظهر تنبيه للمدير فقط.
 */
// define('LICENSE_URL',   'https://console.example.com/ping.php');
// define('LICENSE_TOKEN', '...');


/* ==================================== (ب) SaaS ===================================
 *
 * احذف قسم (أ) بالكامل واستخدم ما يلي بدلًا منه.
 *
 * قبل التشغيل:
 *   1) سجل DNS يوجّه *.نطاقك لهذا السيرفر، وشهادة SSL شاملة (wildcard).
 *   2) مستخدم MySQL واحد له صلاحية على كل قواعد العيادات:
 *        CREATE USER 'app'@'localhost' IDENTIFIED BY '...';
 *        GRANT ALL PRIVILEGES ON `clinic\_%`.* TO 'app'@'localhost';
 *        GRANT ALL PRIVILEGES ON `console`.*  TO 'app'@'localhost';
 *        GRANT CREATE, DROP ON *.* TO 'app'@'localhost';   -- لإنشاء قواعد العيادات
 *      البادئة clinic_ يجب أن تطابق «بادئة أسماء القواعد» في إعدادات الكونسول.
 *   3) اضبط النطاق الأساسي وأيام التجربة من إعدادات الكونسول.
 *
 * العزل بين العيادات يقع عند الاتصال: كل عيادة قاعدة مستقلة، فلا يوجد استعلام
 * يمكن أن يُنسى فيه فلتر العيادة فتظهر بيانات عيادة لأخرى.
 */

// define('SAAS_MODE', true);
// define('SAAS_DB_HOST', 'localhost');
// define('SAAS_DB_NAME', 'console');      // قاعدة التحكم (نفس قاعدة الكونسول)
// define('SAAS_DB_USER', 'app');
// define('SAAS_DB_PASS', '...');
// define('SAAS_BASE_DOMAIN', 'myclinic.app');
// define('SAAS_GRACE_DAYS', 7);           // مهلة بعد الانتهاء قبل إيقاف الإضافة
// define('SAAS_BILLING_URL', 'https://console.myclinic.app/pay.php');
// define('APP_TIMEZONE', 'Africa/Cairo');
