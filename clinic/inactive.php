<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('inactive.view');

/*
 * المرضى المتوقفون عن المتابعة.
 *
 * «آخر نشاط» = أحدث تاريخ بين آخر زيارة تمّت، وآخر قياس، وآخر جرعة حقن،
 * وآخر دفعة. لو مرّ عليه أكثر من المدة المحددة فالمريض متوقف ويستحق متابعة.
 */

$days = (int)($_GET['days'] ?? setting('inactive_days', '45'));
$days = max(7, min(365, $days));
$cutoff = date('Y-m-d', strtotime("-$days days"));

$docFilter = (int)($_GET['doctor'] ?? 0);
[$df, $dfArgs] = doctor_filter('p');

$extra = '';
$extraArgs = [];
if (!doctor_scoped() && $docFilter > 0) {
    $extra = ' AND p.doctor_id = ?';
    $extraArgs[] = $docFilter;
}

$sql = "
    SELECT p.id, p.code, p.name, p.phone, p.goal, p.created_at, u.name AS doctor_name,
        (SELECT MAX(a.adate) FROM appointments a WHERE a.patient_id = p.id AND a.status = 'done') AS last_visit,
        (SELECT MAX(m.mdate) FROM measurements m WHERE m.patient_id = p.id) AS last_measure,
        (SELECT MAX(i.dose_date) FROM injection_doses i WHERE i.patient_id = p.id) AS last_dose,
        (SELECT MAX(pay.pdate) FROM payments pay WHERE pay.patient_id = p.id) AS last_pay,
        (SELECT m2.weight FROM measurements m2 WHERE m2.patient_id = p.id ORDER BY m2.mdate DESC, m2.id DESC LIMIT 1) AS last_weight,
        (SELECT COUNT(*) FROM appointments a2 WHERE a2.patient_id = p.id AND a2.status = 'scheduled' AND a2.adate >= CURDATE()) AS upcoming
    FROM patients p
    LEFT JOIN users u ON u.id = p.doctor_id
    WHERE 1=1 $df $extra
";
$st = $pdo->prepare($sql);
$st->execute([...$dfArgs, ...$extraArgs]);

$all = $st->fetchAll();
$rows = [];
foreach ($all as $r) {
    // المرضى الذين لديهم موعد قادم ليسوا متوقفين
    if ((int)$r['upcoming'] > 0) {
        continue;
    }
    $dates = array_filter([
        $r['last_visit'], $r['last_measure'], $r['last_dose'], $r['last_pay'],
        substr((string)$r['created_at'], 0, 10),
    ]);
    $last = $dates ? max($dates) : null;
    if ($last === null || $last > $cutoff) {
        continue;
    }
    $rows[] = $r + [
        'last_activity' => $last,
        'days_since'    => (int)floor((strtotime(date('Y-m-d')) - strtotime($last)) / 86400),
        'never_visited' => !$r['last_visit'] && !$r['last_measure'] && !$r['last_dose'],
    ];
}
usort($rows, fn($a, $b) => $b['days_since'] <=> $a['days_since']);

$doctors = doctors_list($pdo);
$neverCount = count(array_filter($rows, fn($r) => $r['never_visited']));

page_header('المرضى المتوقفون عن المتابعة', 'inactive.php');
?>
<div class="card">
    <div class="card-head">
        <h2>😴 لم يزوروا العيادة منذ <?= $days ?> يومًا</h2>
        <form class="inline-form" method="get">
            <label>المدة (يوم)
                <select name="days" onchange="this.form.submit()">
                    <?php foreach ([30, 45, 60, 90, 120, 180] as $d): ?>
                        <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>><?= $d ?> يوم</option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!doctor_scoped()): ?>
            <label>الطبيب
                <select name="doctor" onchange="this.form.submit()">
                    <option value="">كل الأطباء</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= (int)$doc['id'] ?>" <?= $docFilter === (int)$doc['id'] ? 'selected' : '' ?>><?= e($doc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php endif; ?>
        </form>
    </div>
    <div class="stats" style="margin-bottom:0">
        <div class="stat accent"><div class="label">متوقفون</div><div class="value"><?= count($rows) ?></div></div>
        <div class="stat"><div class="label">لم يبدأوا المتابعة أصلًا</div><div class="value"><?= $neverCount ?></div></div>
        <div class="stat"><div class="label">فرصة استرجاع</div>
            <div class="value" style="font-size:17px">تواصل معهم بضغطة واتساب</div></div>
    </div>
    <p class="muted" style="margin-top:12px">
        المريض يظهر هنا إذا لم يسجَّل له نشاط (زيارة أو قياس أو جرعة أو دفعة) خلال المدة المحددة،
        <strong>ولم يكن لديه موعد قادم محجوز</strong>.
    </p>
</div>

<div class="card">
    <div class="card-head">
        <h2>القائمة</h2>
        <?php if (can('export.data')): ?>
            <a class="btn btn-xls btn-sm" href="export.php?type=inactive&days=<?= $days ?>&doctor=<?= $docFilter ?>">⬇ تصدير Excel</a>
        <?php endif; ?>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>المريض</th><th>الطبيب</th><th>الهاتف</th><th>آخر نشاط</th>
            <th>منذ</th><th>آخر وزن</th><th>الهدف</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $wa = wa_phone($r['phone']);
            $msg = 'مرحبًا ' . $r['name'] . " 🌿\n"
                 . 'افتقدناك في ' . setting('clinic_name', 'العيادة') . "!\n"
                 . 'مرّ وقت على آخر متابعة — يسعدنا حجز موعد لمتابعة تقدّمك.'
                 . (setting('clinic_phone') ? "\nللحجز: " . setting('clinic_phone') : '');
        ?>
            <tr>
                <td><a href="patient.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['name']) ?></strong></a>
                    <small class="muted"><?= e($r['code']) ?></small>
                    <?php if ($r['never_visited']): ?><br><span class="badge warn">لم يبدأ المتابعة</span><?php endif; ?></td>
                <td><?= $r['doctor_name'] ? e($r['doctor_name']) : '<span class="muted">—</span>' ?></td>
                <td class="num" dir="ltr"><?= e($r['phone'] ?: '—') ?></td>
                <td class="num"><?= e(fmt_date($r['last_activity'])) ?></td>
                <td class="num">
                    <span class="badge <?= $r['days_since'] > 120 ? 'bad' : ($r['days_since'] > 60 ? 'warn' : 'muted') ?>">
                        <?= $r['days_since'] ?> يوم</span></td>
                <td class="num"><?= $r['last_weight'] !== null ? e(num_fmt($r['last_weight'])) . ' كجم' : '—' ?></td>
                <td><?= e(mb_substr((string)$r['goal'], 0, 30)) ?></td>
                <td><div class="actions">
                    <?php if ($wa): ?>
                        <a class="btn btn-sm btn-wa" target="_blank" rel="noopener"
                           href="<?= e(wa_link($wa, $msg)) ?>">💬 تواصل</a>
                    <?php endif; ?>
                    <?php if (can('appt.manage')): ?>
                        <a class="btn btn-light btn-sm" href="appointments.php?patient=<?= (int)$r['id'] ?>">حجز موعد</a>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
            <tr><td colspan="8" class="muted">ممتاز — لا يوجد مرضى متوقفون خلال هذه المدة ✔</td></tr>
        <?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
