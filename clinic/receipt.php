<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('receipt.print');

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT pay.*, p.name AS pname, p.code, p.phone, p.doctor_id, u.name AS uname
     FROM payments pay
     LEFT JOIN patients p ON p.id = pay.patient_id
     LEFT JOIN users u ON u.id = pay.created_by
     WHERE pay.id = ?'
);
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    flash('الدفعة غير موجودة.', 'danger');
    redirect('payments.php');
}
if ($r['patient_id'] && !can_access_patient(['doctor_id' => $r['doctor_id']])) {
    flash('هذا المريض تحت رعاية طبيب آخر.', 'danger');
    redirect('payments.php');
}

/** تفقيط المبلغ بالعربية — الإيصال الرسمي يحتاج المبلغ كتابةً */
function amount_words(float $amount): string
{
    $ones = ['', 'واحد', 'اثنان', 'ثلاثة', 'أربعة', 'خمسة', 'ستة', 'سبعة', 'ثمانية', 'تسعة',
             'عشرة', 'أحد عشر', 'اثنا عشر', 'ثلاثة عشر', 'أربعة عشر', 'خمسة عشر',
             'ستة عشر', 'سبعة عشر', 'ثمانية عشر', 'تسعة عشر'];
    $tens = ['', '', 'عشرون', 'ثلاثون', 'أربعون', 'خمسون', 'ستون', 'سبعون', 'ثمانون', 'تسعون'];
    $hund = ['', 'مائة', 'مائتان', 'ثلاثمائة', 'أربعمائة', 'خمسمائة',
             'ستمائة', 'سبعمائة', 'ثمانمائة', 'تسعمائة'];

    $under1000 = function (int $n) use ($ones, $tens, $hund): string {
        $parts = [];
        if (intdiv($n, 100) > 0) {
            $parts[] = $hund[intdiv($n, 100)];
        }
        $rest = $n % 100;
        if ($rest > 0) {
            if ($rest < 20) {
                $parts[] = $ones[$rest];
            } else {
                $u = $rest % 10;
                $parts[] = $u > 0 ? $ones[$u] . ' و' . $tens[intdiv($rest, 10)] : $tens[intdiv($rest, 10)];
            }
        }
        return implode(' و', $parts);
    };

    $whole = (int)floor($amount);
    $piastres = (int)round(($amount - $whole) * 100);

    if ($whole === 0) {
        $text = 'صفر';
    } else {
        $chunks = [];
        $millions = intdiv($whole, 1000000);
        $thousands = intdiv($whole % 1000000, 1000);
        $remainder = $whole % 1000;

        if ($millions > 0) {
            $chunks[] = match (true) {
                $millions === 1 => 'مليون',
                $millions === 2 => 'مليونان',
                $millions < 11  => $under1000($millions) . ' ملايين',
                default         => $under1000($millions) . ' مليون',
            };
        }
        if ($thousands > 0) {
            $chunks[] = match (true) {
                $thousands === 1 => 'ألف',
                $thousands === 2 => 'ألفان',
                $thousands < 11  => $under1000($thousands) . ' آلاف',
                default          => $under1000($thousands) . ' ألف',
            };
        }
        if ($remainder > 0) {
            $chunks[] = $under1000($remainder);
        }
        $text = implode(' و', $chunks);
    }

    $out = $text . ' ' . setting('currency', 'ج.م');
    if ($piastres > 0) {
        $out .= ' و' . $under1000($piastres) . ' قرشًا';
    }
    return $out . ' فقط لا غير';
}

$serial = 'R-' . str_pad((string)$r['id'], 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>إيصال <?= e($serial) ?></title>
<link rel="stylesheet" href="assets/cairo.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; color: #0f172a; font-size: 14px; line-height: 1.7; background: #f1f5f9; }
.sheet { max-width: 620px; margin: 20px auto; background: #fff; padding: 28px 32px; border-radius: 10px; border: 1px solid #e2e8f0; }
.head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
.head h1 { font-size: 19px; color: #0f766e; }
.sub { color: #64748b; font-size: 12px; }
.serial { text-align: left; }
.serial .no { font-size: 17px; font-weight: 700; color: #0f766e; font-variant-numeric: tabular-nums; }
h2.rt { font-size: 16px; text-align: center; background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 8px; padding: 8px; margin-bottom: 16px; }
table.kv { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
table.kv td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
table.kv td:first-child { color: #64748b; width: 130px; }
table.kv td:last-child { font-weight: 700; }
.amount { background: #0f766e; color: #fff; border-radius: 8px; padding: 14px 18px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; }
.amount .l { font-size: 13px; opacity: .85; }
.amount .v { font-size: 25px; font-weight: 700; font-variant-numeric: tabular-nums; }
.words { border: 1px dashed #cbd5e1; border-radius: 8px; padding: 10px 14px; margin-bottom: 18px; font-size: 13.5px; }
.words b { color: #115e59; }
.foot { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 26px; color: #64748b; font-size: 12px; }
.sign { text-align: center; }
.sign .line { margin-top: 34px; border-top: 1px dotted #64748b; padding-top: 4px; width: 170px; }
.toolbar { max-width: 620px; margin: 16px auto 0; display: flex; gap: 8px; }
.btn { background: #0f766e; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
.btn.light { background: #e2e8f0; color: #0f172a; }
@media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .sheet { margin: 0; border: none; border-radius: 0; padding: 0 6px; max-width: none; }
}
</style>
</head>
<body>
<div class="toolbar">
    <button class="btn" onclick="window.print()">🖨️ طباعة الإيصال</button>
    <a class="btn light" href="payments.php">المدفوعات</a>
    <?php if ($r['patient_id']): ?>
        <a class="btn light" href="patient.php?id=<?= (int)$r['patient_id'] ?>&tab=pay">ملف المريض</a>
    <?php endif; ?>
</div>

<div class="sheet">
    <div class="head">
        <div>
            <h1>🍏 <?= e(setting('clinic_name', 'عيادة التغذية')) ?></h1>
            <div class="sub"><?= e(setting('clinic_address')) ?></div>
            <?php if (setting('clinic_phone')): ?>
                <div class="sub">☎ <span dir="ltr"><?= e(setting('clinic_phone')) ?></span></div>
            <?php endif; ?>
        </div>
        <div class="serial">
            <div class="sub">رقم الإيصال</div>
            <div class="no" dir="ltr"><?= e($serial) ?></div>
            <div class="sub"><?= e(fmt_date($r['pdate'])) ?></div>
        </div>
    </div>

    <h2 class="rt">إيصال استلام نقدية</h2>

    <table class="kv">
        <tr><td>استلمنا من</td><td><?= e($r['pname'] ?? 'عميل نقدي') ?>
            <?php if ($r['code']): ?><span class="sub">(<?= e($r['code']) ?>)</span><?php endif; ?></td></tr>
        <?php if ($r['phone']): ?>
        <tr><td>الهاتف</td><td dir="ltr"><?= e($r['phone']) ?></td></tr>
        <?php endif; ?>
        <tr><td>مقابل</td><td><?= e($r['service'] ?: 'خدمات العيادة') ?>
            <?php if ($r['notes']): ?><br><span class="sub" style="font-weight:400"><?= e($r['notes']) ?></span><?php endif; ?></td></tr>
        <tr><td>طريقة الدفع</td><td><?= e(PAY_METHODS[$r['method']] ?? $r['method']) ?></td></tr>
    </table>

    <div class="amount">
        <span class="l">المبلغ المستلم</span>
        <span class="v"><?= e(money($r['amount'])) ?></span>
    </div>

    <div class="words">
        <b>وقدره كتابةً:</b> <?= e(amount_words((float)$r['amount'])) ?>
    </div>

    <div class="foot">
        <div>
            <?= e(setting('print_note')) ?><br>
            <span class="sub">حرّره: <?= e($r['uname'] ?? '—') ?></span>
        </div>
        <div class="sign"><div class="line">توقيع المستلم وختم العيادة</div></div>
    </div>
</div>
</body>
</html>
