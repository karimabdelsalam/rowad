<?php
/**
 * عودة العميل من صفحة باي موب.
 *
 * هذه الصفحة تعرض النتيجة فقط ولا تعتمد عليها في تأكيد أي دفعة — التأكيد يأتي
 * من paymob_callback.php الموقّع. لو وصل العميل هنا قبل الـ callback تظهر له
 * رسالة انتظار بدل ادعاء نجاح لم يُتحقق منه.
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/paymob.php';

$orderId = (string)($_GET['order'] ?? '');
$claimsSuccess = ($_GET['success'] ?? '') === 'true';

$pay = null;
if ($orderId !== '') {
    $st = $pdo->prepare("SELECT p.*, i.number, i.pay_token FROM payments p
                         LEFT JOIN invoices i ON i.id = p.invoice_id
                         WHERE p.gateway_order_id = ? AND p.method = 'paymob' ORDER BY p.id DESC LIMIT 1");
    $st->execute([$orderId]);
    $pay = $st->fetch() ?: null;
}
$confirmed = $pay && $pay['status'] === 'confirmed';
$failed = ($pay && $pay['status'] === 'failed') || (!$claimsSuccess && $pay);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نتيجة السداد</title>
<link rel="stylesheet" href="assets/console.css">
<?php if (!$confirmed && !$failed): ?><meta http-equiv="refresh" content="5"><?php endif; ?>
</head>
<body class="auth-body">
<div class="auth-card">
    <?php if ($confirmed): ?>
        <h1 class="auth-title">✅ تم السداد</h1>
        <div class="alert alert-success">
            وصلنا سدادك بنجاح<?= $pay['number'] ? ' لفاتورة ' . e($pay['number']) : '' ?>، وتم تفعيل اشتراكك.
        </div>
    <?php elseif ($failed): ?>
        <h1 class="auth-title">لم تتم العملية</h1>
        <div class="alert alert-danger">لم تكتمل عملية الدفع ولم يُخصم منك شيء. يمكنك المحاولة مرة أخرى.</div>
        <?php if ($pay && $pay['pay_token']): ?>
            <a class="btn" href="pay.php?t=<?= e($pay['pay_token']) ?>">العودة لصفحة السداد</a>
        <?php endif; ?>
    <?php else: ?>
        <h1 class="auth-title">⏳ جارٍ التأكيد</h1>
        <div class="alert alert-warning">
            استلمنا العملية وننتظر تأكيد البنك. تُحدَّث هذه الصفحة تلقائيًا — لا داعي لإعادة الدفع.
        </div>
    <?php endif; ?>
    <?php if (setting('support_phone') !== ''): ?>
        <p class="muted">لأي استفسار: <a href="tel:<?= e(setting('support_phone')) ?>" dir="ltr"><?= e(setting('support_phone')) ?></a></p>
    <?php endif; ?>
</div>
</body>
</html>
