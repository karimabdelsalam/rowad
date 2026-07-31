<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    $date = date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $back = 'appointments.php?date=' . urlencode($_POST['back_date'] ?? $date);

    if ($action === 'add') {
        $pid = posted_patient_id($pdo);
        if (!$pid) {
            flash('اختر المريض من قائمة البحث.', 'danger');
            redirect($back);
        }
        $type = array_key_exists($_POST['type'] ?? '', APPT_TYPES) ? $_POST['type'] : 'followup';
        $st = $pdo->prepare('INSERT INTO appointments (patient_id, adate, atime, type, notes, created_by) VALUES (?,?,?,?,?,?)');
        $st->execute([
            $pid,
            ($_POST['adate'] ?? '') ?: date('Y-m-d'),
            ($_POST['atime'] ?? '') ?: '12:00',
            $type,
            trim($_POST['notes'] ?? ''),
            user()['id'],
        ]);
        flash('تم حجز الموعد.');
        redirect('appointments.php?date=' . urlencode($_POST['adate'] ?: $date));
    }

    if ($action === 'status') {
        $status = array_key_exists($_POST['status'] ?? '', APPT_STATUS) ? $_POST['status'] : 'scheduled';
        $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ?')
            ->execute([$status, (int)$_POST['aid']]);
        redirect($back);
    }

    if ($action === 'delete' && has_role('admin', 'reception')) {
        $pdo->prepare('DELETE FROM appointments WHERE id = ?')->execute([(int)$_POST['aid']]);
        flash('تم حذف الموعد.');
        redirect($back);
    }
}

// شريط الأسبوع: من السبت
$dow = (int)date('w', strtotime($date)); // 0=أحد … 6=سبت
$weekStart = date('Y-m-d', strtotime($date . ' -' . (($dow + 1) % 7) . ' days'));
$weekDays = [];
for ($i = 0; $i < 7; $i++) {
    $weekDays[] = date('Y-m-d', strtotime($weekStart . " +$i days"));
}
$st = $pdo->prepare(
    "SELECT adate, COUNT(*) c FROM appointments
     WHERE adate BETWEEN ? AND ? AND status IN ('scheduled','done') GROUP BY adate"
);
$st->execute([$weekDays[0], $weekDays[6]]);
$counts = $st->fetchAll(PDO::FETCH_KEY_PAIR);

$st = $pdo->prepare(
    'SELECT a.*, p.name AS pname, p.phone, p.code FROM appointments a
     JOIN patients p ON p.id = a.patient_id WHERE a.adate = ? ORDER BY a.atime'
);
$st->execute([$date]);
$appts = $st->fetchAll();

$prefPatient = isset($_GET['patient']) ? (int)$_GET['patient'] : null;

page_header('المواعيد', 'appointments.php');
?>
<div class="week-strip">
    <?php foreach ($weekDays as $d): ?>
        <a href="appointments.php?date=<?= $d ?>"
           class="<?= $d === $date ? 'selected' : '' ?> <?= $d === date('Y-m-d') ? 'today' : '' ?>">
            <div class="d-name"><?= e(day_ar($d)) ?></div>
            <div><?= e(date('d/m', strtotime($d))) ?></div>
            <div class="d-count"><?= (int)($counts[$d] ?? 0) ?> موعد</div>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-head">
        <h2>حجز موعد جديد</h2>
        <form class="inline-form" method="get">
            <input type="date" name="date" value="<?= e($date) ?>">
            <button class="btn btn-light btn-sm" type="submit">عرض يوم آخر</button>
            <a class="btn btn-sm btn-wa" href="reminders.php?date=<?= e($date) ?>">💬 تذكيرات واتساب</a>
            <a class="btn btn-xls btn-sm" href="export.php?type=appointments&from=<?= e($weekDays[0]) ?>&to=<?= e($weekDays[6]) ?>">⬇ تصدير الأسبوع</a>
        </form>
    </div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="back_date" value="<?= e($date) ?>">
        <div class="grid4">
            <label>المريض * <?= patient_picker($pdo, 'patient_id', $prefPatient) ?></label>
            <label>التاريخ <input type="date" name="adate" value="<?= e($date) ?>" required></label>
            <label>الوقت <input type="time" name="atime" value="12:00" required></label>
            <label>النوع
                <select name="type">
                    <?php foreach (APPT_TYPES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>ملاحظات <input name="notes"></label>
        <button class="btn" type="submit">حجز الموعد</button>
        <?php if (!patient_options($pdo)): ?>
            <p class="muted">لا يوجد مرضى مسجلون بعد — <a href="patients.php?new=1">سجّل مريضًا أولًا</a>.</p>
        <?php endif; ?>
    </form>
</div>

<div class="card">
    <h2>مواعيد <?= e(day_ar($date)) ?> <?= e(fmt_date($date)) ?> (<?= count($appts) ?>)</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المريض</th><th>الهاتف</th><th>النوع</th><th>الحالة</th><th>ملاحظات</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($appts as $a): ?>
            <tr>
                <td class="num"><strong><?= e(fmt_time($a['atime'])) ?></strong></td>
                <td><a href="patient.php?id=<?= (int)$a['patient_id'] ?>"><?= e($a['pname']) ?></a>
                    <small class="muted"><?= e($a['code']) ?></small></td>
                <td class="num" dir="ltr"><?= e($a['phone']) ?></td>
                <td><?= e(APPT_TYPES[$a['type']] ?? $a['type']) ?></td>
                <td><span class="badge <?= e(APPT_BADGE[$a['status']]) ?>"><?= e(APPT_STATUS[$a['status']]) ?></span></td>
                <td><?= e($a['notes']) ?></td>
                <td><div class="actions">
                    <?php $wa = wa_phone($a['phone']); if ($wa && $a['status'] === 'scheduled'): ?>
                        <a class="btn btn-sm btn-wa" href="<?= e(wa_link($wa, wa_message($a))) ?>"
                           target="_blank" rel="noopener" title="إرسال تذكير واتساب">💬</a>
                    <?php endif; ?>
                    <?php
                    $btns = $a['status'] === 'scheduled'
                        ? ['done' => '✔ تم', 'no_show' => 'لم يحضر', 'cancelled' => 'إلغاء']
                        : ['scheduled' => 'إعادة للحجز'];
                    foreach ($btns as $s => $label): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="status">
                            <input type="hidden" name="back_date" value="<?= e($date) ?>">
                            <input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
                            <input type="hidden" name="status" value="<?= e($s) ?>">
                            <button class="btn btn-light btn-sm" type="submit"><?= e($label) ?></button>
                        </form>
                    <?php endforeach; ?>
                    <?php if (has_role('admin', 'reception')): ?>
                        <form method="post" data-confirm="حذف الموعد نهائيًا؟">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="back_date" value="<?= e($date) ?>">
                            <input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                        </form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$appts): ?><tr><td colspan="7" class="muted">لا توجد مواعيد في هذا اليوم.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
