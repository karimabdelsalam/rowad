<?php
/**
 * فاحص جاهزية السيرفر — افتحه قبل التثبيت للتأكد أن الاستضافة تدعم كل ما يلزم.
 * لا يحتاج قاعدة بيانات ولا تسجيل دخول. احذفه بعد التثبيت مع install.php.
 */
declare(strict_types=1);
mb_internal_encoding('UTF-8');

/*
 * قبل التثبيت الصفحة مفتوحة (لا يوجد حساب بعد). بعد التثبيت تتطلب تسجيل دخول
 * مدير، حتى لا تكشف تفاصيل السيرفر للعامة إذا نُسي حذف الملف.
 */
if (is_file(__DIR__ . '/inc/config.php')) {
    require __DIR__ . '/inc/bootstrap.php';
    require_login();
    require_perm('settings.manage');
}

function row(string $label, bool $ok, string $detail, bool $required = true): array
{
    return compact('label', 'ok', 'detail', 'required');
}

$checks = [];

$phpOk = PHP_VERSION_ID >= 80000;
$checks[] = row('إصدار PHP', $phpOk, PHP_VERSION . ($phpOk ? '' : ' — المطلوب 8.0 أو أحدث'));

foreach ([
    'pdo_mysql' => ['الاتصال بقاعدة البيانات', true],
    'mbstring'  => ['معالجة النصوص العربية', true],
    'zip'       => ['تصدير ملفات Excel (بدونه يصدّر CSV)', false],
    'curl'      => ['إرسال التذكيرات التلقائية (بدونه يستخدم بديلًا)', false],
    'json'      => ['تبادل البيانات', true],
] as $ext => [$why, $required]) {
    $checks[] = row('امتداد ' . $ext, extension_loaded($ext), $why, $required);
}

$configDir = __DIR__ . '/inc';
$writable = is_writable($configDir);
$checks[] = row(
    'إمكانية الكتابة في مجلد inc/',
    $writable,
    $writable ? 'المثبّت سينشئ config.php تلقائيًا' : 'أنشئ inc/config.php يدويًا (المثبّت سيعرض محتواه)',
    false
);

$installed = is_file($configDir . '/config.php');
$checks[] = row('حالة التثبيت', true, $installed ? 'النظام مثبت بالفعل' : 'غير مثبت بعد — افتح install.php', false);

$installExists = is_file(__DIR__ . '/install.php');
$checks[] = row(
    'ملف install.php',
    !($installed && $installExists),
    $installed && $installExists
        ? '⚠ النظام مثبت والملف ما زال موجودًا — احذفه الآن لأسباب أمنية'
        : ($installExists ? 'موجود — احذفه بعد التثبيت' : 'محذوف ✔'),
    false
);

$checkExists = true;
$checks[] = row('ملف check.php (هذه الصفحة)', !$installed, $installed ? '⚠ احذفه أيضًا بعد التثبيت' : 'احذفه بعد التثبيت', false);

// اختبار الاتصال بقاعدة البيانات إن كان مثبتًا
if ($installed) {
    // $pdo متاح بالفعل من bootstrap الذي حُمّل في أعلى الملف
    try {
        $tables = (int)$pdo->query(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . $pdo->quote(current_db_name())
        )->fetchColumn();
        $checks[] = row('الاتصال بقاعدة البيانات', true, 'ناجح — ' . $tables . ' جدول');
    } catch (PDOException) {
        $checks[] = row('الاتصال بقاعدة البيانات', false, 'فشل الاستعلام على قاعدة البيانات');
    }
}

$fontOk = is_file(__DIR__ . '/assets/fonts/cairo-arabic-400.woff2');
$checks[] = row('ملفات خط Cairo', $fontOk, $fontOk ? 'موجودة' : 'ناقصة — سيظهر خط بديل', false);

$failed = array_filter($checks, fn($c) => !$c['ok'] && $c['required']);
$warned = array_filter($checks, fn($c) => !$c['ok'] && !$c['required']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>فحص جاهزية السيرفر</title>
<link rel="stylesheet" href="assets/cairo.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;background:#f1f5f9;color:#0f172a;padding:20px;line-height:1.7}
.wrap{max-width:720px;margin:0 auto}
h1{font-size:21px;margin-bottom:4px;color:#0f766e}
.sub{color:#64748b;font-size:13px;margin-bottom:18px}
.card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin-bottom:16px}
table{width:100%;border-collapse:collapse}
td{padding:10px 8px;border-bottom:1px solid #e2e8f0;vertical-align:top}
td:first-child{font-weight:700;width:190px}
td:last-child{color:#64748b;font-size:13px}
.st{width:34px;font-size:17px;text-align:center}
.banner{padding:14px 18px;border-radius:10px;font-weight:700;margin-bottom:16px}
.ok{background:#dcfce7;color:#15803d}
.bad{background:#fee2e2;color:#b91c1c}
.warn{background:#fef3c7;color:#92400e}
.btn{display:inline-block;background:#0f766e;color:#fff;padding:10px 20px;border-radius:8px;font-weight:700;text-decoration:none}
</style>
</head>
<body>
<div class="wrap">
    <h1>🍏 فحص جاهزية السيرفر</h1>
    <div class="sub">نظام إدارة عيادة التغذية والتخسيس</div>

    <?php if ($failed): ?>
        <div class="banner bad">✘ السيرفر ينقصه <?= count($failed) ?> متطلب أساسي — راجع الجدول بالأسفل.</div>
    <?php elseif ($warned): ?>
        <div class="banner warn">✔ السيرفر جاهز للتشغيل، مع <?= count($warned) ?> ملاحظة غير حرجة.</div>
    <?php else: ?>
        <div class="banner ok">✔ السيرفر جاهز تمامًا — كل المتطلبات متوفرة.</div>
    <?php endif; ?>

    <div class="card">
        <table>
        <?php foreach ($checks as $c): ?>
            <tr>
                <td class="st"><?= $c['ok'] ? '✅' : ($c['required'] ? '❌' : '⚠️') ?></td>
                <td><?= htmlspecialchars($c['label']) ?></td>
                <td><?= htmlspecialchars($c['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </table>
    </div>

    <div class="card">
        <?php if (!$installed): ?>
            <p style="margin-bottom:12px">الخطوة التالية: تثبيت النظام وإنشاء الجداول وحساب المدير.</p>
            <a class="btn" href="install.php">بدء التثبيت ←</a>
        <?php else: ?>
            <p style="margin-bottom:12px">النظام مثبت. <strong>احذف الآن <code>install.php</code> و<code>check.php</code></strong> من السيرفر.</p>
            <a class="btn" href="login.php">تسجيل الدخول ←</a>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
