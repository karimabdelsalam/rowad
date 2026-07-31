<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('appt.remind');

$date = $_GET['date'] ?? date('Y-m-d', strtotime('+1 day'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    $date = date('Y-m-d', strtotime('+1 day'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $back = 'reminders.php?date=' . urlencode($_POST['back_date'] ?? $date);

    if ($action === 'mark') {
        $pdo->prepare('UPDATE appointments SET reminder_sent = NOW() WHERE id = ?')->execute([(int)$_POST['aid']]);
        redirect($back);
    }

    if ($action === 'unmark') {
        $pdo->prepare('UPDATE appointments SET reminder_sent = NULL WHERE id = ?')->execute([(int)$_POST['aid']]);
        redirect($back);
    }

    if ($action === 'mark_all') {
        $st = $pdo->prepare("UPDATE appointments SET reminder_sent = NOW() WHERE adate = ? AND status = 'scheduled' AND reminder_sent IS NULL");
        $st->execute([$_POST['back_date'] ?? $date]);
        flash('تم تعليم ' . $st->rowCount() . ' موعد كمُرسَل.');
        redirect($back);
    }
}

$st = $pdo->prepare(
    "SELECT a.*, p.name AS pname, p.phone, p.code FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     WHERE a.adate = ? AND a.status = 'scheduled' ORDER BY a.atime"
);
$st->execute([$date]);
$appts = $st->fetchAll();

$pending = array_filter($appts, fn($a) => $a['reminder_sent'] === null);
$noPhone = array_filter($appts, fn($a) => wa_phone($a['phone']) === null);

page_header('تذكير المواعيد عبر واتساب', 'reminders.php');
?>
<div class="card">
    <div class="card-head">
        <h2>💬 تذكيرات <?= e(day_ar($date)) ?> <?= e(fmt_date($date)) ?></h2>
        <form class="inline-form" method="get">
            <a class="btn btn-light btn-sm" href="reminders.php?date=<?= date('Y-m-d') ?>">اليوم</a>
            <a class="btn btn-light btn-sm" href="reminders.php?date=<?= date('Y-m-d', strtotime('+1 day')) ?>">بكرة</a>
            <input type="date" name="date" value="<?= e($date) ?>">
            <button class="btn btn-sm" type="submit">عرض</button>
        </form>
    </div>
    <p class="muted">
        اضغط زر واتساب بجانب كل مريض — هيفتح المحادثة والرسالة مكتوبة جاهزة، وتُعلَّم تلقائيًا كـ«تم الإرسال».
        الرسالة تتعدّل من <a href="settings.php">الإعدادات</a>.
    </p>
    <div class="stats" style="margin-bottom:0">
        <div class="stat accent"><div class="label">مواعيد محجوزة</div><div class="value"><?= count($appts) ?></div></div>
        <div class="stat"><div class="label">لم يُرسل لهم بعد</div><div class="value"><?= count($pending) ?></div></div>
        <?php if ($noPhone): ?>
        <div class="stat"><div class="label">بدون رقم صالح</div><div class="value" style="color:#b91c1c"><?= count($noPhone) ?></div></div>
        <?php endif; ?>
    </div>
</div>

<?php if (!$appts): ?>
<div class="card"><p class="muted">لا توجد مواعيد محجوزة في هذا اليوم.</p></div>
<?php else: ?>
<div class="card">
    <div class="card-head">
        <h2>قائمة التذكيرات</h2>
        <?php if ($pending): ?>
        <form method="post" data-confirm="تعليم كل المواعيد المتبقية كمُرسَلة بدون فتح واتساب؟">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all">
            <input type="hidden" name="back_date" value="<?= e($date) ?>">
            <button class="btn btn-light btn-sm" type="submit">تعليم الكل كمُرسَل</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المريض</th><th>الهاتف</th><th>النوع</th><th>التذكير</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($appts as $a):
            $phone = wa_phone($a['phone']);
            $msg = wa_message($a);
        ?>
            <tr>
                <td class="num"><strong><?= e(fmt_time($a['atime'])) ?></strong></td>
                <td><a href="patient.php?id=<?= (int)$a['patient_id'] ?>"><?= e($a['pname']) ?></a>
                    <small class="muted"><?= e($a['code']) ?></small></td>
                <td class="num" dir="ltr"><?= e($a['phone'] ?: '—') ?></td>
                <td><?= e(APPT_TYPES[$a['type']] ?? $a['type']) ?></td>
                <td>
                    <?php if ($a['reminder_sent']): ?>
                        <span class="badge ok">أُرسل <?= e(date('d/m', strtotime($a['reminder_sent']))) ?></span>
                    <?php else: ?>
                        <span class="badge mut">لم يُرسل</span>
                    <?php endif; ?>
                </td>
                <td><div class="actions">
                    <?php if ($phone): ?>
                        <a class="btn btn-sm btn-wa" href="<?= e(wa_link($phone, $msg)) ?>" target="_blank" rel="noopener"
                           data-aid="<?= (int)$a['id'] ?>" title="فتح واتساب برسالة جاهزة">💬 واتساب</a>
                        <button class="btn btn-light btn-sm btn-copy" type="button"
                                data-msg="<?= e($msg) ?>" title="نسخ نص الرسالة">نسخ الرسالة</button>
                    <?php else: ?>
                        <span class="badge bad">رقم غير صالح</span>
                        <a class="btn btn-light btn-sm" href="patients.php?edit=<?= (int)$a['patient_id'] ?>">تعديل الرقم</a>
                    <?php endif; ?>
                    <form method="post" class="mark-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $a['reminder_sent'] ? 'unmark' : 'mark' ?>">
                        <input type="hidden" name="back_date" value="<?= e($date) ?>">
                        <input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-light btn-sm" type="submit"><?= $a['reminder_sent'] ? 'تراجع' : '✔ تم يدويًا' ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <h2>معاينة نص الرسالة</h2>
    <pre style="background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:14px;white-space:pre-wrap;font:inherit"><?= e(wa_message($appts[0])) ?></pre>
    <p class="muted">هذه معاينة على أول موعد في القائمة. لتغيير الصيغة افتح <a href="settings.php">الإعدادات</a>.</p>
</div>
<?php endif; ?>

<script>
// فتح واتساب في تبويب جديد ثم تعليم الموعد كمُرسَل وتحديث الصفحة
document.querySelectorAll('.btn-wa').forEach(function (link) {
    link.addEventListener('click', function () {
        var form = link.closest('.actions').querySelector('.mark-form');
        if (!form || form.querySelector('[name=action]').value !== 'mark') return;
        setTimeout(function () { form.submit(); }, 600);
    });
});

// نسخ نص الرسالة للحافظة
document.querySelectorAll('.btn-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var msg = btn.dataset.msg, done = function () {
            var old = btn.textContent;
            btn.textContent = 'تم النسخ ✔';
            setTimeout(function () { btn.textContent = old; }, 1600);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(msg).then(done, function () { fallback(msg, done); });
        } else {
            fallback(msg, done);
        }
    });
});
function fallback(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) { prompt('انسخ الرسالة:', text); }
    document.body.removeChild(ta);
}
</script>
<?php page_footer();
