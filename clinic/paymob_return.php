<?php
/**
 * عودة المريض من صفحة الدفع.
 *
 * تعرض النتيجة فقط ولا تؤكد شيئًا — التأكيد يأتي من الإشعار الموقّع. لو وصل
 * المريض هنا قبل الإشعار تظهر له رسالة انتظار بدل ادعاء نجاح لم يُتحقق منه.
 */
require __DIR__ . '/inc/bootstrap.php';

$orderId = (string)($_GET['order'] ?? '');
$req = null;
if ($orderId !== '') {
    $st = $pdo->prepare('SELECT * FROM payment_requests WHERE gateway_order_id = ? ORDER BY id DESC LIMIT 1');
    $st->execute([$orderId]);
    $req = $st->fetch() ?: null;
}
$paid = $req && $req['status'] === 'paid';
$failed = $req && $req['status'] === 'failed';
$clinic = setting('clinic_name', 'العيادة');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>نتيجة الدفع | <?= e($clinic) ?></title>
<link rel="stylesheet" href="assets/clinic.css">
<?php if ($req && !$paid && !$failed): ?><meta http-equiv="refresh" content="5"><?php endif; ?>
</head>
<body class="auth-body">
<div class="auth-card">
    <h1 class="auth-title">🍏 <?= e($clinic) ?></h1>
    <?php if ($paid): ?>
        <div class="alert alert-success">✅ تم استلام مبلغ
            <strong><?= e(money($req['amount'])) ?></strong> بنجاح. شكرًا لك.</div>
    <?php elseif ($failed): ?>
        <div class="alert alert-danger">لم تكتمل العملية ولم يُخصم منك شيء. يمكنك المحاولة مرة أخرى.</div>
        <?php if ($req): ?><a class="btn" href="paylink.php?t=<?= e($req['token']) ?>">العودة لصفحة الدفع</a><?php endif; ?>
    <?php elseif ($req): ?>
        <div class="alert alert-warning">⏳ استلمنا العملية وننتظر تأكيد البنك.
            تُحدَّث هذه الصفحة تلقائيًا — لا داعي لإعادة الدفع.</div>
    <?php else: ?>
        <div class="alert alert-warning">لم نتعرف على هذه العملية. راجع العيادة للتأكد.</div>
    <?php endif; ?>
    <?php if (setting('clinic_phone') !== ''): ?>
        <p class="muted" style="text-align:center">للتواصل:
            <a href="tel:<?= e(setting('clinic_phone')) ?>" dir="ltr"><?= e(setting('clinic_phone')) ?></a></p>
    <?php endif; ?>
</div>
</body>
</html>
