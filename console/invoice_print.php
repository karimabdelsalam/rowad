<?php
/** فاتورة للطباعة / حفظ PDF — تُفتح من صفحة الفاتورة وتُطبع من المتصفح. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT i.*, c.name AS clinic_name, c.owner_name, c.phone, c.email
                     FROM invoices i JOIN clinics c ON c.id = i.clinic_id WHERE i.id = ?');
$st->execute([$id]);
$inv = $st->fetch();
if (!$inv) {
    flash('الفاتورة غير موجودة.', 'danger');
    redirect('invoices.php');
}

$pays = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? AND status = 'confirmed' ORDER BY pdate, id");
$pays->execute([$id]);
$pays = $pays->fetchAll();

$remaining = max(0, (float)$inv['amount'] - (float)$inv['paid']);
$brand = setting('brand_name', 'Planova بلانوفا');
$payUrl = rtrim(setting('console_url', ''), '/');
$payUrl = $payUrl !== '' ? $payUrl . '/pay.php?t=' . $inv['pay_token'] : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>فاتورة <?= e($inv['number']) ?> | <?= e($brand) ?></title>
<link rel="stylesheet" href="assets/console.css">
<style>
body { background: #fff; }
.sheet { max-width: 760px; margin: 0 auto; padding: 34px 26px; }
.inv-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px;
            border-bottom: 3px solid var(--primary); padding-bottom: 18px; margin-bottom: 22px; }
.inv-brand .b1 { font-size: 24px; font-weight: 700; color: var(--primary); }
.inv-brand .b2 { color: var(--muted); font-size: 13px; }
.inv-no { text-align: left; }
.inv-no .n1 { font-size: 20px; font-weight: 700; }
.inv-no .n2 { font-family: ui-monospace, monospace; color: var(--muted); }
.stamp { display: inline-block; padding: 4px 18px; border: 2px solid; border-radius: 8px;
         font-weight: 700; transform: rotate(-4deg); margin-top: 8px; }
.stamp.paid { color: #15803d; border-color: #15803d; }
.stamp.due  { color: var(--danger); border-color: var(--danger); }
.two-col { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 22px; }
.box { border: 1px solid var(--line); border-radius: 10px; padding: 12px 15px; font-size: 14px; }
.box .t { color: var(--muted); font-size: 12px; margin-bottom: 4px; }
table.lines { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.lines th { background: var(--primary); color: #fff; padding: 9px 12px; text-align: right; font-size: 13px; }
.lines td { padding: 10px 12px; border-bottom: 1px solid var(--line); }
.totals { max-width: 300px; margin-inline-start: auto; font-size: 14.5px; }
.totals div { display: flex; justify-content: space-between; padding: 6px 4px; }
.totals .grand { border-top: 2px solid var(--ink, #1c2333); font-weight: 700; font-size: 17px; }
.totals .rem strong { color: var(--danger); }
.footer-note { margin-top: 30px; padding-top: 14px; border-top: 1px solid var(--line);
               color: var(--muted); font-size: 12.5px; text-align: center; line-height: 1.9; }
.no-print { text-align: center; margin: 18px 0; display: flex; gap: 8px; justify-content: center; }
@media print { .no-print { display: none; } .sheet { padding: 0; } body { font-size: 13px; } }
</style>
</head>
<body>
<div class="sheet">

    <div class="no-print">
        <button class="btn" onclick="window.print()">🖨️ طباعة / حفظ PDF</button>
        <a class="btn btn-light" href="invoice.php?id=<?= $id ?>">رجوع للفاتورة</a>
    </div>

    <div class="inv-head">
        <div class="inv-brand">
            <div class="b1">💼 <?= e($brand) ?></div>
            <div class="b2">نظام Pclinic بي كلينك لإدارة العيادات
                <?php if (setting('support_phone') !== ''): ?>
                    · <span dir="ltr"><?= e(setting('support_phone')) ?></span>
                <?php endif; ?></div>
        </div>
        <div class="inv-no">
            <div class="n1">فاتورة</div>
            <div class="n2"><?= e($inv['number']) ?></div>
            <?php if ($inv['status'] === 'paid'): ?>
                <span class="stamp paid">مدفوعة ✔</span>
            <?php elseif ($inv['status'] === 'void'): ?>
                <span class="stamp due">ملغاة</span>
            <?php else: ?>
                <span class="stamp due">مستحقة</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="two-col">
        <div class="box">
            <div class="t">فاتورة إلى</div>
            <strong><?= e($inv['clinic_name']) ?></strong><br>
            <?= e($inv['owner_name'] ?: '') ?><br>
            <span dir="ltr"><?= e($inv['phone'] ?: '') ?></span>
            <?= $inv['email'] ? '<br><span dir="ltr">' . e($inv['email']) . '</span>' : '' ?>
        </div>
        <div class="box">
            <div class="t">بيانات الفاتورة</div>
            تاريخ الإصدار: <strong><?= e(fmt_date($inv['issue_date'])) ?></strong><br>
            تاريخ الاستحقاق: <strong><?= e(fmt_date($inv['due_date'])) ?></strong>
        </div>
    </div>

    <table class="lines">
        <thead><tr><th>البيان</th><th style="width:110px">المدة</th><th style="width:130px">المبلغ</th></tr></thead>
        <tbody>
            <tr>
                <td>اشتراك <?= e($inv['plan_name'] ?: 'نظام Pclinic') ?>
                    <?= $inv['notes'] ? '<br><small class="muted">' . e($inv['notes']) . '</small>' : '' ?></td>
                <td><?= (int)$inv['months'] > 0 ? (int)$inv['months'] . ' شهر' : '—' ?></td>
                <td><?= e(money($inv['amount'])) ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($pays): ?>
    <table class="lines">
        <thead><tr><th>الدفعات المستلمة</th><th style="width:110px">الوسيلة</th><th style="width:130px">المبلغ</th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?>
            <tr>
                <td><?= e(fmt_date($p['pdate'])) ?>
                    <?= $p['reference'] ? ' — <span dir="ltr">' . e($p['reference']) . '</span>' : '' ?></td>
                <td><?= e(PAY_METHODS[$p['method']] ?? $p['method']) ?></td>
                <td><?= e(money($p['amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div class="totals">
        <div><span>الإجمالي</span><span><?= e(money($inv['amount'])) ?></span></div>
        <div><span>المسدد</span><span><?= e(money($inv['paid'])) ?></span></div>
        <div class="grand <?= $remaining > 0.005 ? 'rem' : '' ?>">
            <span>المتبقي</span><strong><?= e(money($remaining)) ?></strong></div>
    </div>

    <div class="footer-note">
        <?php if ($remaining > 0.005 && $payUrl !== '' && $inv['status'] !== 'void'): ?>
            للسداد أونلاين: <span dir="ltr"><?= e($payUrl) ?></span><br>
        <?php endif; ?>
        شكرًا لثقتكم في <?= e($brand) ?> 🌿
    </div>

</div>
</body>
</html>
