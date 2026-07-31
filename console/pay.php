<?php
/**
 * صفحة السداد العامة — يفتحها صاحب العيادة برابط الفاتورة بدون حساب.
 *
 * الرابط يحمل رمزًا عشوائيًا (pay_token) لا رقمًا متسلسلًا، فلا يمكن تصفّح
 * فواتير الآخرين بتخمين الرقم.
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/paymob.php';

$token = (string)($_GET['t'] ?? '');
$st = $pdo->prepare('SELECT i.*, c.name AS clinic_name, c.owner_name, c.phone, c.email
                     FROM invoices i JOIN clinics c ON c.id = i.clinic_id WHERE i.pay_token = ?');
$st->execute([$token]);
$inv = $st->fetch();

if (!$inv || strlen($token) < 20) {
    http_response_code(404);
    $notFound = true;
} else {
    $notFound = false;
    $remaining = (float)$inv['amount'] - (float)$inv['paid'];
}

$error = '';
$iframeUrl = '';

if (!$notFound && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'paymob') {
    csrf_verify();
    if ($inv['status'] === 'paid') {
        $error = 'هذه الفاتورة مسددة بالكامل.';
    } elseif ($inv['status'] === 'void') {
        $error = 'هذه الفاتورة ملغاة.';
    } else {
        try {
            $res = paymob_start($inv, [
                'name' => $inv['clinic_name'], 'owner_name' => $inv['owner_name'],
                'phone' => $inv['phone'], 'email' => $inv['email'],
            ]);
            // تُسجَّل المحاولة معلّقة ليربطها الـ callback برقم الطلب
            $pdo->prepare('INSERT INTO payments (invoice_id, clinic_id, pdate, amount, method, status,
                           gateway_order_id, notes) VALUES (?,?,?,?,?,?,?,?)')
                ->execute([
                    (int)$inv['id'], (int)$inv['clinic_id'], date('Y-m-d'), $remaining,
                    'paymob', 'pending', $res['order_id'], 'محاولة سداد أونلاين',
                ]);
            $iframeUrl = $res['iframe_url'];
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
}

$brand = setting('brand_name', 'Planova بلانوفا');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $notFound ? 'فاتورة غير موجودة' : 'سداد فاتورة ' . e($inv['number']) ?></title>
<link rel="stylesheet" href="assets/console.css">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:680px">

<?php if ($notFound): ?>
    <h1 class="auth-title">الرابط غير صالح</h1>
    <div class="alert alert-danger">لم نجد فاتورة بهذا الرابط. تأكد من نسخه كاملًا أو راجعنا.</div>

<?php elseif ($iframeUrl): ?>
    <h1 class="auth-title">إتمام السداد</h1>
    <p class="muted">أكمل الدفع في النافذة التالية. لا تغلقها قبل ظهور رسالة النجاح.</p>
    <iframe src="<?= e($iframeUrl) ?>" style="width:100%;height:640px;border:1px solid #e2e8f0;border-radius:12px"></iframe>

<?php else: ?>
    <h1 class="auth-title">💳 سداد اشتراك</h1>
    <p class="muted"><?= e($brand) ?></p>

    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="pay-summary">
        <div><span class="muted">العيادة</span><strong><?= e($inv['clinic_name']) ?></strong></div>
        <div><span class="muted">رقم الفاتورة</span><strong dir="ltr"><?= e($inv['number']) ?></strong></div>
        <?php if ($inv['plan_name']): ?>
            <div><span class="muted">الاشتراك</span><strong><?= e($inv['plan_name']) ?></strong></div>
        <?php endif; ?>
        <div><span class="muted">إجمالي الفاتورة</span><strong><?= e(money($inv['amount'])) ?></strong></div>
        <div class="total"><span>المطلوب سداده</span><strong><?= e(money(max(0, $remaining))) ?></strong></div>
    </div>

    <?php if ($inv['status'] === 'paid'): ?>
        <div class="alert alert-success">✔ هذه الفاتورة مسددة بالكامل. شكرًا لك.</div>

    <?php elseif ($inv['status'] === 'void'): ?>
        <div class="alert alert-warning">هذه الفاتورة ملغاة، لا حاجة لسدادها.</div>

    <?php else: ?>
        <h3 class="form-section">اختر طريقة الدفع</h3>

        <?php if (paymob_configured()): ?>
        <form method="post" class="pay-option">
            <?= csrf_field() ?><input type="hidden" name="action" value="paymob">
            <div>
                <strong>💳 بطاقة بنكية / محفظة — أونلاين</strong>
                <small class="muted">يُفعَّل اشتراكك فور نجاح العملية.</small>
            </div>
            <button class="btn" type="submit">ادفع الآن</button>
        </form>
        <?php endif; ?>

        <?php if (setting('instapay_addr') !== ''): ?>
        <div class="pay-option">
            <div>
                <strong>📲 إنستا باي</strong>
                <small class="muted"><?= e(setting('instapay_note')) ?></small>
                <div class="copy-row" style="margin-top:8px">
                    <input value="<?= e(setting('instapay_addr')) ?>" dir="ltr" readonly onclick="this.select()">
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="pay-option">
            <div>
                <strong>💵 كاش</strong>
                <small class="muted">سلّم المبلغ لمندوبنا أو في المقر، وسيُسجَّل في حسابك فورًا.</small>
            </div>
        </div>

        <?php if (setting('support_phone') !== ''): ?>
            <p class="muted" style="margin-top:16px">لأي استفسار:
                <a href="tel:<?= e(setting('support_phone')) ?>" dir="ltr"><?= e(setting('support_phone')) ?></a></p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
