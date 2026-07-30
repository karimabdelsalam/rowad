<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$totalPatients = (int)$pdo->query('SELECT COUNT(*) FROM patients')->fetchColumn();

$st = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE created_at >= ?');
$st->execute([$monthStart]);
$newPatients = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE adate = ? AND status IN ('scheduled','done')");
$st->execute([$today]);
$todayAppts = (int)$st->fetchColumn();

$canSeeMoney = has_role('admin', 'reception');
$todayIncome = $monthIncome = 0.0;
if ($canSeeMoney) {
    $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pdate = ?');
    $st->execute([$today]);
    $todayIncome = (float)$st->fetchColumn();

    $st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pdate >= ?');
    $st->execute([$monthStart]);
    $monthIncome = (float)$st->fetchColumn();
}

$st = $pdo->prepare(
    'SELECT a.*, p.name AS pname, p.phone FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     WHERE a.adate = ? ORDER BY a.atime'
);
$st->execute([$today]);
$appts = $st->fetchAll();

$recent = $pdo->query('SELECT id, code, name, phone, created_at FROM patients ORDER BY id DESC LIMIT 6')->fetchAll();

page_header('لوحة التحكم', 'index.php');
?>
<div class="stats">
    <div class="stat accent"><div class="label">مواعيد اليوم</div><div class="value"><?= $todayAppts ?></div></div>
    <div class="stat"><div class="label">إجمالي المرضى</div><div class="value"><?= $totalPatients ?></div></div>
    <div class="stat"><div class="label">مرضى جدد هذا الشهر</div><div class="value"><?= $newPatients ?></div></div>
    <?php if ($canSeeMoney): ?>
    <div class="stat"><div class="label">إيراد اليوم</div><div class="value"><?= e(money($todayIncome)) ?></div></div>
    <div class="stat"><div class="label">إيراد الشهر</div><div class="value"><?= e(money($monthIncome)) ?></div></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h2>📅 مواعيد اليوم — <?= e(day_ar($today)) ?> <?= e(fmt_date($today)) ?></h2>
        <a class="btn btn-sm" href="appointments.php">إدارة المواعيد</a>
    </div>
    <?php if (!$appts): ?>
        <p class="muted">لا توجد مواعيد اليوم.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المريض</th><th>الهاتف</th><th>النوع</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($appts as $a): ?>
            <tr>
                <td class="num"><?= e(fmt_time($a['atime'])) ?></td>
                <td><a href="patient.php?id=<?= (int)$a['patient_id'] ?>"><?= e($a['pname']) ?></a></td>
                <td class="num" dir="ltr"><?= e($a['phone']) ?></td>
                <td><?= e(APPT_TYPES[$a['type']] ?? $a['type']) ?></td>
                <td><span class="badge <?= e(APPT_BADGE[$a['status']]) ?>"><?= e(APPT_STATUS[$a['status']]) ?></span></td>
                <td><a class="btn btn-light btn-sm" href="patient.php?id=<?= (int)$a['patient_id'] ?>">الملف</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h2>👥 أحدث المرضى</h2>
        <a class="btn btn-sm" href="patients.php?new=1">+ مريض جديد</a>
    </div>
    <?php if (!$recent): ?>
        <p class="muted">لم يُسجَّل أي مريض بعد — ابدأ بإضافة أول مريض.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>الكود</th><th>الاسم</th><th>الهاتف</th><th>تاريخ التسجيل</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $p): ?>
            <tr>
                <td class="num"><?= e($p['code']) ?></td>
                <td><a href="patient.php?id=<?= (int)$p['id'] ?>"><?= e($p['name']) ?></a></td>
                <td class="num" dir="ltr"><?= e($p['phone']) ?></td>
                <td class="num"><?= e(fmt_date($p['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php page_footer();
