<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('plan.view');

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare(
    'SELECT d.*, p.name AS pname, p.code, p.phone, p.height_cm, p.birth_date, p.gender, p.doctor_id
     FROM diet_plans d JOIN patients p ON p.id = d.patient_id WHERE d.id = ?'
);
$st->execute([$id]);
$pl = $st->fetch();
if (!$pl) {
    flash('النظام الغذائي غير موجود.', 'danger');
    redirect('plans.php');
}
if (!can_access_patient(['doctor_id' => $pl['doctor_id'] ?? null])) {
    flash('هذا المريض تحت رعاية طبيب آخر.', 'danger');
    redirect('plans.php');
}

$st = $pdo->prepare('SELECT weight FROM measurements WHERE patient_id = ? ORDER BY mdate DESC, id DESC LIMIT 1');
$st->execute([(int)$pl['patient_id']]);
$lastWeight = $st->fetchColumn();

$age = calc_age($pl['birth_date']);
$meals = [
    ['🍳', 'الفطار', $pl['breakfast']],
    ['🍎', 'سناك صباحي', $pl['snack1']],
    ['🍗', 'الغداء', $pl['lunch']],
    ['🥜', 'سناك مسائي', $pl['snack2']],
    ['🥗', 'العشاء', $pl['dinner']],
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>طباعة: <?= e($pl['title']) ?> — <?= e($pl['pname']) ?></title>
<link rel="stylesheet" href="assets/cairo.css">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; color: #0f172a; font-size: 14px; line-height: 1.7; background: #f1f5f9; }
.sheet { max-width: 800px; margin: 20px auto; background: #fff; padding: 32px 36px; border-radius: 10px; }
.head { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #0f766e; padding-bottom: 12px; margin-bottom: 16px; }
.head h1 { font-size: 20px; color: #0f766e; }
.head .sub { color: #64748b; font-size: 12px; }
.meta { display: flex; flex-wrap: wrap; gap: 6px 24px; background: #f0fdfa; border: 1px solid #ccfbf1; border-radius: 8px; padding: 10px 14px; margin-bottom: 16px; }
.meta span { white-space: nowrap; }
.meta b { color: #115e59; }
h2.plan-title { font-size: 17px; margin-bottom: 12px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th, td { border: 1px solid #cbd5e1; padding: 9px 12px; text-align: right; vertical-align: top; }
th { background: #0f766e; color: #fff; width: 140px; white-space: nowrap; }
td { white-space: pre-line; }
.box { border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; margin-bottom: 12px; white-space: pre-line; }
.box.warn { border-color: #fca5a5; background: #fef2f2; }
.box h3 { font-size: 14px; margin-bottom: 4px; }
.footer { display: flex; justify-content: space-between; margin-top: 28px; color: #64748b; font-size: 12px; }
.sign { text-align: center; }
.sign .line { margin-top: 34px; border-top: 1px dotted #64748b; padding-top: 4px; width: 180px; }
.toolbar { max-width: 800px; margin: 16px auto 0; display: flex; gap: 8px; }
.btn { background: #0f766e; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font: inherit; font-weight: 700; cursor: pointer; text-decoration: none; }
.btn.light { background: #e2e8f0; color: #0f172a; }
@media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0; border-radius: 0; padding: 0 6px; max-width: none; } }
</style>
</head>
<body>
<div class="toolbar">
    <button class="btn" onclick="window.print()">🖨️ طباعة</button>
    <a class="btn light" href="plan_edit.php?id=<?= $id ?>">تعديل</a>
    <a class="btn light" href="patient.php?id=<?= (int)$pl['patient_id'] ?>&tab=plans">ملف المريض</a>
</div>
<div class="sheet">
    <div class="head">
        <div>
            <h1>🍏 <?= e(setting('clinic_name', 'عيادة التغذية')) ?></h1>
            <div class="sub"><?= e(setting('clinic_address')) ?></div>
        </div>
        <div class="sub" style="text-align:left">
            <?php if (setting('clinic_phone')): ?>☎ <span dir="ltr"><?= e(setting('clinic_phone')) ?></span><br><?php endif; ?>
            تاريخ الطباعة: <?= e(fmt_date(date('Y-m-d'))) ?>
        </div>
    </div>

    <div class="meta">
        <span>الاسم: <b><?= e($pl['pname']) ?></b></span>
        <span>الكود: <b><?= e($pl['code']) ?></b></span>
        <?php if ($age !== null): ?><span>العمر: <b><?= $age ?> سنة</b></span><?php endif; ?>
        <?php if ($pl['height_cm']): ?><span>الطول: <b><?= e($pl['height_cm']) ?> سم</b></span><?php endif; ?>
        <?php if ($lastWeight): ?><span>الوزن الحالي: <b><?= e($lastWeight) ?> كجم</b></span><?php endif; ?>
        <?php if ($pl['calories']): ?><span>السعرات: <b><?= e($pl['calories']) ?> سعر/يوم</b></span><?php endif; ?>
    </div>

    <h2 class="plan-title"><?= e($pl['title']) ?>
        <small style="color:#64748b;font-weight:400">
            (من <?= e(fmt_date($pl['start_date'])) ?><?= $pl['end_date'] ? ' إلى ' . e(fmt_date($pl['end_date'])) : '' ?>)
        </small>
    </h2>

    <table>
        <?php foreach ($meals as [$ico, $name, $content]): if (!trim((string)$content)) continue; ?>
        <tr><th><?= $ico ?> <?= e($name) ?></th><td><?= e($content) ?></td></tr>
        <?php endforeach; ?>
    </table>

    <?php if (trim((string)$pl['forbidden'])): ?>
    <div class="box warn"><h3>🚫 الممنوعات</h3><?= e($pl['forbidden']) ?></div>
    <?php endif; ?>
    <?php if (trim((string)$pl['notes'])): ?>
    <div class="box"><h3>📌 تعليمات عامة</h3><?= e($pl['notes']) ?></div>
    <?php endif; ?>

    <div class="footer">
        <div><?= e(setting('print_note')) ?></div>
        <div class="sign"><div class="line">توقيع الأخصائي</div></div>
    </div>
</div>
</body>
</html>
