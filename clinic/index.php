<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

[$df, $dfArgs] = doctor_filter('p');

$st = $pdo->prepare("SELECT COUNT(*) FROM patients p WHERE 1=1 $df");
$st->execute($dfArgs);
$totalPatients = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM patients p WHERE p.created_at >= ? $df");
$st->execute([$monthStart, ...$dfArgs]);
$newPatients = (int)$st->fetchColumn();

$st = $pdo->prepare("SELECT COUNT(*) FROM appointments a JOIN patients p ON p.id = a.patient_id
                     WHERE a.adate = ? AND a.status IN ('scheduled','done') $df");
$st->execute([$today, ...$dfArgs]);
$todayAppts = (int)$st->fetchColumn();

$canSeeMoney = can('pay.view');
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
    "SELECT a.*, p.name AS pname, p.phone FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     WHERE a.adate = ? $df ORDER BY a.atime"
);
$st->execute([$today, ...$dfArgs]);
$appts = $st->fetchAll();

/* ------------------------------------- تأكيد حضور مواعيد اليوم وبكرة */
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$st = $pdo->prepare(
    "SELECT a.adate, a.confirm_status, COUNT(*) c
     FROM appointments a JOIN patients p ON p.id = a.patient_id
     WHERE a.adate IN (?, ?) AND a.status = 'scheduled' $df
     GROUP BY a.adate, a.confirm_status"
);
$st->execute([$today, $tomorrow, ...$dfArgs]);
$confirmStats = ['today' => [], 'tomorrow' => []];
foreach ($st->fetchAll() as $r) {
    $key = $r['adate'] === $today ? 'today' : 'tomorrow';
    $confirmStats[$key][$r['confirm_status']] = (int)$r['c'];
}
$pendingToday    = (int)($confirmStats['today']['pending'] ?? 0) + (int)($confirmStats['today']['no_answer'] ?? 0);
$pendingTomorrow = (int)($confirmStats['tomorrow']['pending'] ?? 0) + (int)($confirmStats['tomorrow']['no_answer'] ?? 0);
$confirmedToday  = (int)($confirmStats['today']['confirmed'] ?? 0);
$tomorrowTotal   = array_sum($confirmStats['tomorrow']);

$st = $pdo->prepare("SELECT p.id, p.code, p.name, p.phone, p.created_at FROM patients p
                     WHERE 1=1 $df ORDER BY p.id DESC LIMIT 6");
$st->execute($dfArgs);
$recent = $st->fetchAll();

/* ------------------------------------------------- تنبيهات ومتابعة الحقن */
$hasInj = module_on('injections');
$dueSoon = [];
$injDebt = 0.0;
if ($hasInj) {
    $st = $pdo->prepare(
        "SELECT pl.id, pl.patient_id, pl.weekly_units, pl.start_date, p.name AS pname, d.name AS drug_name,
            (SELECT MAX(dose_date) FROM injection_doses i WHERE i.plan_id = pl.id) AS last_dose
         FROM injection_plans pl JOIN patients p ON p.id = pl.patient_id JOIN drugs d ON d.id = pl.drug_id
         WHERE pl.status = 'active' $df"
    );
    $st->execute($dfArgs);
    foreach ($st->fetchAll() as $pl) {
        $next = $pl['last_dose'] ? date('Y-m-d', strtotime($pl['last_dose'] . ' +7 days')) : $pl['start_date'];
        if ($next <= date('Y-m-d', strtotime('+2 days'))) {
            $dueSoon[] = $pl + ['next_date' => $next];
        }
    }
    usort($dueSoon, fn($a, $b) => strcmp($a['next_date'], $b['next_date']));

    $st = $pdo->prepare("SELECT COALESCE(SUM(i.amount - i.paid), 0) FROM injection_doses i
                         JOIN patients p ON p.id = i.patient_id WHERE 1=1 $df");
    $st->execute($dfArgs);
    $injDebt = (float)$st->fetchColumn();
}

$lowStock = ($hasInj && can('drug.view')) ? $pdo->query(
    'SELECT d.name, d.low_units,
        COALESCE((SELECT SUM(units_total - units_used) FROM drug_batches b WHERE b.drug_id = d.id), 0) AS units_left
     FROM drugs d WHERE d.active = 1
     HAVING units_left <= d.low_units ORDER BY units_left'
)->fetchAll() : [];

$expiring = ($hasInj && can('drug.view')) ? $pdo->query(
    "SELECT b.*, d.name AS drug_name FROM drug_batches b JOIN drugs d ON d.id = b.drug_id
     WHERE b.units_total > b.units_used AND b.expiry_date IS NOT NULL
       AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY)
     ORDER BY b.expiry_date"
)->fetchAll() : [];

page_header('لوحة التحكم', 'index.php');
?>
<?php if (setting('setup_done', '0') !== '1' && can('settings.manage')): ?>
<div class="card" style="border-color:#0f766e">
    <h2>👋 خطوة أخيرة قبل ما تبدأ</h2>
    <p>العيادة لسه على الإعدادات الافتراضية. معالج التهيئة هيمشي معك في 4 خطوات سريعة:
        بيانات العيادة، الوحدات اللي تحتاجها، فريق العمل، والأسعار والباقات.</p>
    <div class="actions">
        <a class="btn" href="setup.php">ابدأ التهيئة (دقيقتين)</a>
        <form method="post" action="setup.php">
            <?= csrf_field() ?><input type="hidden" name="step" value="5">
            <input type="hidden" name="skip" value="1">
            <button class="btn btn-light" type="submit">اتخطاها — هظبطها بنفسي</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="stats">
    <div class="stat accent"><div class="label">مواعيد اليوم</div><div class="value"><?= $todayAppts ?></div></div>
    <div class="stat"><div class="label">إجمالي المرضى</div><div class="value"><?= $totalPatients ?></div></div>
    <div class="stat"><div class="label">مرضى جدد هذا الشهر</div><div class="value"><?= $newPatients ?></div></div>
    <?php if ($canSeeMoney): ?>
    <div class="stat"><div class="label">إيراد اليوم</div><div class="value"><?= e(money($todayIncome)) ?></div></div>
    <div class="stat"><div class="label">إيراد الشهر</div><div class="value"><?= e(money($monthIncome)) ?></div></div>
    <?php endif; ?>
</div>

<?php if (can('appt.remind') && ($pendingToday > 0 || $pendingTomorrow > 0)): ?>
    <?php if ($pendingToday > 0): ?>
    <div class="alert alert-danger">
        📞 <strong><?= $pendingToday ?></strong> من مواعيد <strong>اليوم</strong> لم يتأكد حضورهم بعد —
        <a href="reminders.php?date=<?= $today ?>">اتصل بهم الآن</a>
    </div>
    <?php endif; ?>
    <?php if ($pendingTomorrow > 0): ?>
    <div class="alert alert-warning">
        📅 <strong><?= $pendingTomorrow ?></strong> من مواعيد <strong>الغد</strong> (<?= e(day_ar($tomorrow)) ?>)
        تحتاج اتصال تأكيد — <a href="reminders.php?date=<?= $tomorrow ?>">افتح قائمة الاتصال</a>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php foreach ($lowStock as $s): ?>
    <div class="alert alert-warning">📦 مخزون منخفض: <strong><?= e($s['name']) ?></strong> —
        متبقٍ <?= e(num_fmt($s['units_left'])) ?> وحدة فقط.
        <a href="drugs.php?stock=1">استلام كمية</a></div>
<?php endforeach; ?>
<?php foreach ($expiring as $b): ?>
    <div class="alert alert-<?= $b['expiry_date'] < $today ? 'danger' : 'warning' ?>">
        ⏳ <strong><?= e($b['drug_name']) ?></strong>
        <?= $b['expiry_date'] < $today ? 'دفعة منتهية الصلاحية في' : 'دفعة تنتهي في' ?>
        <?= e(fmt_date($b['expiry_date'])) ?> — متبقٍ بها
        <?= e(num_fmt((float)$b['units_total'] - (float)$b['units_used'])) ?> وحدة.
        <a href="drugs.php?stock=1">المخزون</a></div>
<?php endforeach; ?>

<?php if ($dueSoon || $injDebt > 0.005): ?>
<div class="card">
    <div class="card-head">
        <h2>💉 متابعة الحقن</h2>
        <div class="actions">
            <?php if ($injDebt > 0.005): ?>
                <a class="btn btn-light btn-sm" href="injections.php?tab=due">متأخرات: <?= e(money($injDebt)) ?></a>
            <?php endif; ?>
            <a class="btn btn-sm" href="injections.php?tab=give">تسجيل جرعة</a>
        </div>
    </div>
    <?php if (!$dueSoon): ?>
        <p class="muted">لا توجد جرعات مستحقة خلال اليومين القادمين.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>المريض</th><th>الدواء</th><th>الجرعة</th><th>موعدها</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($dueSoon as $r): $overdue = $r['next_date'] < $today; ?>
            <tr>
                <td><a href="patient.php?id=<?= (int)$r['patient_id'] ?>&tab=inj"><?= e($r['pname']) ?></a></td>
                <td><?= e($r['drug_name']) ?></td>
                <td class="num"><strong><?= e(num_fmt($r['weekly_units'])) ?></strong> وحدة</td>
                <td class="num">
                    <?php if ($overdue): ?><span class="badge bad">متأخرة <?= e(fmt_date($r['next_date'])) ?></span>
                    <?php elseif ($r['next_date'] === $today): ?><span class="badge warn">اليوم</span>
                    <?php else: ?><?= e(fmt_date($r['next_date'])) ?><?php endif; ?>
                </td>
                <td><a class="btn btn-light btn-sm" href="injections.php?tab=give">تسجيل</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>📅 مواعيد اليوم — <?= e(day_ar($today)) ?> <?= e(fmt_date($today)) ?>
            <?php if ($appts): ?><small class="muted">(<?= $confirmedToday ?> مؤكد من <?= count($appts) ?>)</small><?php endif; ?>
        </h2>
        <div class="actions">
            <?php if (can('appt.remind')): ?>
                <a class="btn btn-sm" href="reminders.php?date=<?= $today ?>">📞 قائمة الاتصال</a>
            <?php endif; ?>
            <a class="btn btn-light btn-sm" href="appointments.php">إدارة المواعيد</a>
        </div>
    </div>
    <?php if (!$appts): ?>
        <p class="muted">لا توجد مواعيد اليوم.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المريض</th><th>الهاتف</th><th>النوع</th><th>الحالة</th><th>تأكيد الحضور</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($appts as $a): ?>
            <tr>
                <td class="num"><?= e(fmt_time($a['atime'])) ?></td>
                <td><a href="patient.php?id=<?= (int)$a['patient_id'] ?>"><?= e($a['pname']) ?></a></td>
                <td class="num" dir="ltr"><?= e($a['phone']) ?></td>
                <td><?= e(APPT_TYPES[$a['type']] ?? $a['type']) ?></td>
                <td><span class="badge <?= e(APPT_BADGE[$a['status']]) ?>"><?= e(APPT_STATUS[$a['status']]) ?></span></td>
                <td><span class="badge <?= e(CONFIRM_BADGE[$a['confirm_status'] ?? 'pending']) ?>">
                    <?= e(CONFIRM_STATUS[$a['confirm_status'] ?? 'pending']) ?></span></td>
                <td><div class="actions">
                    <?php $tel = tel_link($a['phone']); if ($tel && ($a['confirm_status'] ?? 'pending') !== 'confirmed'): ?>
                        <a class="btn btn-sm" href="<?= e($tel) ?>" title="اتصال">📞</a>
                    <?php endif; ?>
                    <a class="btn btn-light btn-sm" href="patient.php?id=<?= (int)$a['patient_id'] ?>">الملف</a>
                </div></td>
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
