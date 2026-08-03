<?php
/**
 * تقديم المرفقات.
 *
 * الملفات محفوظة خارج متناول المتصفح (مجلد uploads محمي بـ .htaccess)، ولا
 * تُقدَّم إلا عبر هذا الملف بعد التحقق من الصلاحية ومن أن المريض ضمن نطاق
 * المستخدم. اسم الملف على القرص عشوائي ولا يأتي من المستخدم إطلاقًا.
 */
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('files.view');

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT a.*, p.doctor_id FROM attachments a JOIN patients p ON p.id = a.patient_id WHERE a.id = ?'
);
$st->execute([$id]);
$f = $st->fetch();

if (!$f || !can_access_patient(['doctor_id' => $f['doctor_id']])) {
    http_response_code(404);
    exit('الملف غير موجود.');
}

// اسم الملف من قاعدة البيانات فقط، مع فلترة إضافية ضد أي محاولة اجتياز مسار
$stored = basename((string)$f['stored_name']);
$path = uploads_dir() . '/' . $stored;
if (!is_file($path)) {
    http_response_code(404);
    exit('الملف غير موجود على السيرفر.');
}

$mime = (string)$f['mime'];
if (!in_array($mime, FILE_TYPES, true)) {
    $mime = 'application/octet-stream';
}

$download = isset($_GET['download']);
$name = (string)$f['original_name'] ?: $stored;

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: ' . ($download ? 'attachment' : 'inline')
    . '; filename="' . preg_replace('/[^\p{Arabic}\p{L}\p{N}._\- ]+/u', '', $name) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; img-src \'self\'; object-src \'none\'');
header('Cache-Control: private, max-age=3600');
readfile($path);
