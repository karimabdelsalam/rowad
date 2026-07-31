<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    flash('المريض غير موجود.', 'danger');
    redirect('patients.php');
}

$st = $pdo->prepare(
    'SELECT i.*, d.name AS drug_name FROM injection_doses i
     JOIN drugs d ON d.id = i.drug_id WHERE i.patient_id = ? ORDER BY i.dose_date, i.id'
);
$st->execute([$id]);
$doses = $st->fetchAll();

$plan = active_plan($pdo, $id);
$totUnits = array_sum(array_map(fn($r) => (float)$r['units'], $doses));
$totAmount = array_sum(array_map(fn($r) => (float)$r['amount'], $doses));
$totPaid = array_sum(array_map(fn($r) => (float)$r['paid'], $doses));
$balance = round($totAmount - $totPaid, 2);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>كشف حساب الحقن — <?= e($p['name']) ?></title>
<link rel="stylesheet" href="assets/cairo.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; color: #0f172a; font-size: 14px; line-height: 1.7; background: #f1f5f9; }
.sheet { max-width: 820px; margin: 20px auto; background: #fff; padding: 32px 36px; border-radius: 10px; }
.head { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
.head h1 { font-size: 20px; color: #0f766e; }
.sub { color: #64748b; font-size: 12px; }
.meta { display: flex; flex-wrap: wrap; gap: 6px 24px; background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; }
.meta b { color: #115e59; }
.protocol { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 6px 24px; }
h2 { font-size: 16px; margin-bottom: 10px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: right; }
th { background: #0f766e; color: #fff; font-size: 13px; white-space: nowrap; }
td.num { white-space: nowrap; font-variant-numeric: tabular-nums; }
tfoot td { background: #f1f5f9; font-weight: 700; }
.totals { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 18px; }
.tot { flex: 1; min-width: 150px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; }
.tot .l { color: #64748b; font-size: 12px; }
.tot .v { font-size: 19px; font-weight: 700; }
.tot.due { border-color: #fca5a5; background: #fef2f2; }
.tot.ok { border-color: #86efac; background: #f0fdf4; }
.footer { display: flex; justify-content: space-between; margin-top: 26px; color: #64748b; font-size: 12px; }
.sign { margin-top: 32px; border-top: 1px dotted #64748b; padding-top: 4px; width: 180px; text-align: center; }
.toolbar { max-width: 820px; margin: 16px auto 0; display: flex; gap: 8px; }
.btn { background: #0f766e; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
.btn.light { background: #e2e8f0; color: #0f172a; }
@media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; border-radius: 0; padding: 0 6px; max-width: none; } }
</style>
</head>
<body>
<div class="toolbar">
    <button class="btn" onclick="window.print()">🖨️ طباعة</button>
    <a class="btn light" href="patient.php?id=<?= $id ?>&tab=inj">رجوع لملف المريض</a>
</div>
<div class="sheet">
    <div class="head">
        <div>
            <h1>🍏 <?= e(setting('clinic_name', 'عيادة التغذية')) ?></h1>
            <div class="sub"><?= e(setting('clinic_address')) ?></div>
        </div>
        <div class="sub" style="text-align:left">
            <?php if (setting('clinic_phone')): ?>☎ <span dir="ltr"><?= e(setting('clinic_phone')) ?></span><br><?php endif; ?>
            تاريخ الكشف: <?= e(fmt_date(date('Y-m-d'))) ?>
        </div>
    </div>

    <h2>كشف حساب الحقن — المحاسبة بالوحدات</h2>

    <div class="meta">
        <span>الاسم: <b><?= e($p['name']) ?></b></span>
        <span>الكود: <b><?= e($p['code']) ?></b></span>
        <?php if ($p['phone']): ?><span>الهاتف: <b dir="ltr"><?= e($p['phone']) ?></b></span><?php endif; ?>
    </div>

    <?php if ($plan): ?>
    <div class="protocol">
        <span>الدواء: <b><?= e($plan['drug_name']) ?></b></span>
        <span>الجرعة الأسبوعية: <b><?= e(num_fmt($plan['weekly_units'])) ?> وحدة</b></span>
        <span>سعر الوحدة: <b><?= e(money($plan['unit_price'])) ?></b></span>
        <span>تكلفة الأسبوع: <b><?= e(money((float)$plan['weekly_units'] * (float)$plan['unit_price'])) ?></b></span>
        <span>الجرعة القادمة: <b><?= e(fmt_date(next_dose_date($pdo, $plan))) ?></b></span>
    </div>
    <?php endif; ?>

    <div class="totals">
        <div class="tot"><div class="l">إجمالي الوحدات</div><div class="v"><?= e(num_fmt($totUnits)) ?></div></div>
        <div class="tot"><div class="l">إجمالي المستحق</div><div class="v"><?= e(money($totAmount)) ?></div></div>
        <div class="tot"><div class="l">المسدَّد</div><div class="v"><?= e(money($totPaid)) ?></div></div>
        <div class="tot <?= $balance > 0.005 ? 'due' : 'ok' ?>">
            <div class="l"><?= $balance > 0.005 ? 'المتبقي على المريض' : 'الرصيد' ?></div>
            <div class="v"><?= $balance > 0.005 ? e(money($balance)) : 'مسدَّد بالكامل ✔' ?></div>
        </div>
    </div>

    <table>
        <thead><tr><th>م</th><th>التاريخ</th><th>الدواء</th><th>الوحدات</th><th>سعر الوحدة</th>
            <th>المستحق</th><th>المدفوع</th><th>الرصيد</th></tr></thead>
        <tbody>
        <?php $running = 0.0; foreach ($doses as $i => $r):
            $running += (float)$r['amount'] - (float)$r['paid']; ?>
            <tr>
                <td class="num"><?= $i + 1 ?></td>
                <td class="num"><?= e(fmt_date($r['dose_date'])) ?></td>
                <td><?= e($r['drug_name']) ?></td>
                <td class="num"><?= e(num_fmt($r['units'])) ?></td>
                <td class="num"><?= e(money($r['unit_price'])) ?></td>
                <td class="num"><?= e(money($r['amount'])) ?></td>
                <td class="num"><?= e(money($r['paid'])) ?></td>
                <td class="num"><?= e(money($running)) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$doses): ?><tr><td colspan="8" style="color:#64748b">لا توجد جرعات مسجّلة.</td></tr><?php endif; ?>
        </tbody>
        <?php if ($doses): ?>
        <tfoot><tr>
            <td colspan="3">الإجمالي (<?= count($doses) ?> جرعة)</td>
            <td class="num"><?= e(num_fmt($totUnits)) ?></td>
            <td></td>
            <td class="num"><?= e(money($totAmount)) ?></td>
            <td class="num"><?= e(money($totPaid)) ?></td>
            <td class="num"><?= e(money($balance)) ?></td>
        </tr></tfoot>
        <?php endif; ?>
    </table>

    <div class="footer">
        <div><?= e(setting('print_note')) ?></div>
        <div class="sign">توقيع المسؤول</div>
    </div>
</div>
</body>
</html>
