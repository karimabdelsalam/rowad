<?php
/**
 * صفحة دفع المريض — يفتحها برابط بلا حساب.
 *
 * الرابط يحمل رمزًا عشوائيًا لا رقمًا متسلسلًا، فلا يمكن تصفّح مطالبات مرضى
 * آخرين بالتخمين. الصفحة لا تكشف أي بيانات طبية — الاسم والمبلغ والبيان فقط.
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/paymob.php';

$token = (string)($_GET['t'] ?? '');
$req = null;
if (strlen($token) >= 20) {
    $st = $pdo->prepare('SELECT r.*, p.name AS pname, p.phone FROM payment_requests r
                         JOIN patients p ON p.id = r.patient_id WHERE r.token = ?');
    $st->execute([$token]);
    $req = $st->fetch() ?: null;
}

$expired = $req && $req['status'] === 'pending'
        && $req['expires_at'] && $req['expires_at'] < date('Y-m-d H:i:s');

$error = '';
$iframeUrl = '';

if ($req && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'paymob') {
    csrf_verify();
    if ($req['status'] !== 'pending' || $expired) {
        $error = 'هذا الطلب لم يعد صالحًا للدفع.';
    } else {
        try {
            $res = clinic_paymob_start($req, ['name' => $req['pname'], 'phone' => $req['phone']]);
            // رقم الطلب يُحفظ ليربطه الإشعار الموقّع بهذه المطالبة
            $pdo->prepare('UPDATE payment_requests SET gateway_order_id = ? WHERE id = ?')
                ->execute([$res['order_id'], (int)$req['id']]);
            $iframeUrl = $res['iframe_url'];
        } catch (Throwable $ex) {
            $error = $ex->getMessage();
        }
    }
}

$clinic = setting('clinic_name', 'العيادة');
$instapay = setting('clinic_instapay');
$instapayNote = setting('clinic_instapay_note', 'حوّل المبلغ على العنوان الموضح ثم أبلغ العيادة برقم العملية.');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= $req ? 'سداد مستحقات | ' . e($clinic) : 'رابط غير صالح' ?></title>
<link rel="stylesheet" href="assets/clinic.css">
<style>
.pay-sum { border:1px solid var(--line); border-radius:12px; padding:14px; margin:14px 0; }
.pay-sum > div { display:flex; justify-content:space-between; gap:12px; padding:5px 0; font-size:14px; }
.pay-sum .total { border-top:1px solid var(--line); margin-top:6px; padding-top:10px; font-size:18px; }
.pay-sum .total strong { color:var(--primary); }
.opt { display:flex; align-items:center; justify-content:space-between; gap:14px;
       border:1px solid var(--line); border-radius:12px; padding:14px; margin-bottom:10px; }
.opt > div { display:flex; flex-direction:column; gap:3px; }
.opt small { font-weight:400; }
@media (max-width:600px) { .opt { flex-direction:column; align-items:stretch; } }
</style>
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:600px">

<?php if (!$req): ?>
    <h1 class="auth-title">الرابط غير صالح</h1>
    <div class="alert alert-danger">لم نجد مطالبة بهذا الرابط. تأكد من نسخه كاملًا أو تواصل مع العيادة.</div>

<?php elseif ($iframeUrl): ?>
    <h1 class="auth-title">إتمام الدفع</h1>
    <p class="muted">أكمل الدفع في النافذة التالية، ولا تغلقها قبل ظهور النتيجة.</p>
    <iframe src="<?= e($iframeUrl) ?>" style="width:100%;height:640px;border:1px solid #e2e8f0;border-radius:12px"></iframe>

<?php else: ?>
    <h1 class="auth-title">🍏 <?= e($clinic) ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="pay-sum">
        <div><span class="muted">الاسم</span><strong><?= e($req['pname']) ?></strong></div>
        <div><span class="muted">البيان</span><strong><?= e($req['description']) ?></strong></div>
        <div class="total"><span>المطلوب</span><strong><?= e(money($req['amount'])) ?></strong></div>
    </div>

    <?php if ($req['status'] === 'paid'): ?>
        <div class="alert alert-success">✔ تم استلام هذا المبلغ. شكرًا لك.</div>
    <?php elseif ($req['status'] === 'cancelled'): ?>
        <div class="alert alert-warning">أُلغيت هذه المطالبة من العيادة.</div>
    <?php elseif ($expired): ?>
        <div class="alert alert-warning">انتهت صلاحية هذا الرابط — اطلب رابطًا جديدًا من العيادة.</div>
    <?php else: ?>
        <h3 class="form-section">اختر طريقة الدفع</h3>

        <?php if (clinic_paymob_ready()): ?>
        <form method="post" class="opt">
            <?= csrf_field() ?><input type="hidden" name="action" value="paymob">
            <div><strong>💳 بطاقة بنكية / محفظة</strong>
                <small class="muted">دفع فوري ويُسجَّل في حسابك بالعيادة مباشرة.</small></div>
            <button class="btn" type="submit">ادفع الآن</button>
        </form>
        <?php endif; ?>

        <?php if ($instapay !== ''): ?>
        <div class="opt">
            <div><strong>📲 إنستا باي</strong>
                <small class="muted"><?= e($instapayNote) ?></small>
                <input value="<?= e($instapay) ?>" dir="ltr" readonly onclick="this.select()" style="margin-top:8px">
            </div>
        </div>
        <?php endif; ?>

        <div class="opt">
            <div><strong>💵 كاش في العيادة</strong>
                <small class="muted">تقدر تدفع في العيادة عند زيارتك القادمة.</small></div>
        </div>

        <?php if (setting('clinic_phone') !== ''): ?>
            <p class="muted" style="margin-top:14px;text-align:center">لأي استفسار:
                <a href="tel:<?= e(setting('clinic_phone')) ?>" dir="ltr"><?= e(setting('clinic_phone')) ?></a></p>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
