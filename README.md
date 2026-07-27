# Rowad — نظام إدارة العقارات

نظام إدارة عقارات (تأجير/بيع/صيانة/فواتير) مبني بـ PHP و Smarty و MySQL.

## المتطلبات (بعد التحديث)

- **PHP 8.1 أو أحدث** (تم الاختبار على PHP 8.4)
- MySQL / MariaDB مع امتداد `mysqli`
- امتدادات PHP: `mbstring`, `gd`, `curl`, `openssl`, `gettext`, `zip`, `xml`
- [Composer](https://getcomposer.org) لتثبيت المكتبات

## التثبيت

```bash
# 1) تثبيت المكتبات
cd include/lib
composer install

# 2) إعداد ملف الإعدادات المحلي (بيانات قاعدة البيانات والمفتاح السري)
cp include/config.local.sample.php include/config.local.php
# ثم عدّل القيم داخل الملف

# 3) توليد مفتاح سري جديد
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

> **مهم:** ملف `include/config.local.php` غير مُتتبع في git — بيانات الاتصال
> بقاعدة البيانات والمفتاح السري لم تعد داخل الكود. يمكن أيضًا تمريرها عبر
> متغيرات البيئة: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `SECRET_KEY`.

## ما الذي تم تحديثه (2026)

| المكوّن | قبل | بعد |
|---|---|---|
| PHP | 5.x/7.x style | متوافق مع **PHP 8.4** |
| Smarty | 3.1.31 (مدمجة يدويًا) | **4.5** عبر Composer |
| PHPMailer | 5.x (`class.phpmailer.php`) | **6.x** عبر Composer |
| PhpSpreadsheet | 1.25 | **1.30** |
| بيانات الاتصال | مكتوبة داخل `config.php` | ملف محلي / متغيرات بيئة |

تفاصيل التغييرات:

- إزالة نسخ المكتبات المدمجة يدويًا (`include/lib/Smarty`, `class.phpmailer.php`,
  `class.smtp.php`, `class.pop3.php`) والاعتماد على Composer.
- الـ modifiers المخصوصة للقوالب (`clean`, `ardate`, `Period`, `Remain`, `SEO`,
  `coregate`, `help`) انتقلت إلى `include/smarty_plugins/` ويتم تحميلها عبر
  `addPluginsDir()`.
- تسجيل دوال PHP المستخدمة كـ modifiers داخل القوالب صراحةً (متطلب Smarty 4).
- `mysqli_report(MYSQLI_REPORT_OFF)` للحفاظ على سلوك ezSQL القديم مع PHP 8.1+
  (الذي أصبح يرمي استثناءات افتراضيًا).
- إعادة كتابة `sendEmail()` على PHPMailer 6 مع دعم TLS تلقائي حسب المنفذ
  (465 → SMTPS، 587 → STARTTLS) ومعالجة الأخطاء بدل الفشل الصامت.
- إصلاح أخطاء قديمة: استدعاء دالة غير موجودة `http_build_execute()`
  (الصحيح `http_build_query`)، معاملات معكوسة في `date()` داخل modifier
  `Remain`، و`preg_split()` بنمط غير صالح في `string_cut()`.
- التحقق: جميع القوالب (214 ملف `.tpl`) تُتَرجم بنجاح على Smarty 4،
  وجميع ملفات الموديولات (102 ملف) تُحمَّل بدون أخطاء على PHP 8.4.

## ملاحظات للنشر

- ملف `.htaccess` الحالي يحدد `ea-php83` كمعالج PHP على استضافة cPanel —
  اضبط إصدار PHP للاستضافة على 8.1 أو أحدث.
- مجلد `media/cache` يجب أن يكون قابلًا للكتابة (قوالب Smarty المُجمَّعة).
- **يجب تغيير كلمة مرور قاعدة البيانات و`SECRET_KEY`** لأنهما كانا موجودين
  في نسخ سابقة من الكود خارج git.
