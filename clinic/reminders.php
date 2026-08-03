<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('appt.remind');

/*
 * قائمة الاتصال وتأكيد المواعيد.
 *
 * موظف الاستقبال يفتحها قبل يوم من الموعد، يتصل بكل مريض أو يرسل له واتساب،
 * ثم يسجّل النتيجة: أكّد الحضور / لم يرد / اعتذر. الغرض أن تعرف العيادة صباحًا
 * مَن سيحضر فعلًا بدل انتظار مَن قد لا يأتي.
 */

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
        $st = $pdo->prepare("UPDATE appointments SET reminder_sent = NOW()
                             WHERE adate = ? AND status = 'scheduled' AND reminder_sent IS NULL");
        $st->execute([$_POST['back_date'] ?? $date]);
        flash('تم تعليم ' . $st->rowCount() . ' موعد كمُرسَل.');
        redirect($back);
    }

    /* ------------------------------------------ تسجيل نتيجة الاتصال */
    if ($action === 'confirm') {
        deny_unless('appt.confirm', $back);
        $aid = (int)($_POST['aid'] ?? 0);
        $status = array_key_exists($_POST['confirm'] ?? '', CONFIRM_STATUS) ? $_POST['confirm'] : 'pending';

        $st = $pdo->prepare('SELECT a.*, p.name AS pname FROM appointments a
                             JOIN patients p ON p.id = a.patient_id WHERE a.id = ?');
        $st->execute([$aid]);
        $appt = $st->fetch();
        if (!$appt) {
            redirect($back);
        }

        $pdo->prepare('UPDATE appointments SET confirm_status = ?, confirmed_at = NOW(), confirmed_by = ? WHERE id = ?')
            ->execute([$status, user()['id'], $aid]);

        // الاعتذار يعني أن الموعد لن يتم — يُلغى ليُفرَّغ الوقت لمريض آخر
        if ($status === 'declined') {
            $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND status = 'scheduled'")
                ->execute([$aid]);
            flash('سُجّل اعتذار ' . $appt['pname'] . ' وأُلغي الموعد — الوقت متاح الآن لمريض آخر.');
        } else {
            flash('تم تسجيل: ' . CONFIRM_STATUS[$status] . ' — ' . $appt['pname']);
        }
        activity($pdo, 'update', 'appointment', (int)$appt['patient_id'],
            'تأكيد موعد ' . fmt_date($appt['adate']) . ': ' . CONFIRM_STATUS[$status]);
        redirect($back);
    }
}

$st = $pdo->prepare(
    "SELECT a.*, p.name AS pname, p.phone, p.code, u.name AS confirmed_by_name, d.name AS doctor_name
     FROM appointments a
     JOIN patients p ON p.id = a.patient_id
     LEFT JOIN users u ON u.id = a.confirmed_by
     LEFT JOIN users d ON d.id = a.doctor_id
     WHERE a.adate = ? AND a.status IN ('scheduled','cancelled') ORDER BY a.atime"
);
$st->execute([$date]);
$all = $st->fetchAll();

$appts    = array_values(array_filter($all, fn($a) => $a['status'] === 'scheduled'));
$declined = array_values(array_filter($all, fn($a) => $a['status'] === 'cancelled' && $a['confirm_status'] === 'declined'));

$confirmed  = array_filter($appts, fn($a) => $a['confirm_status'] === 'confirmed');
$noContact  = array_filter($appts, fn($a) => $a['confirm_status'] === 'pending');
$noAnswer   = array_filter($appts, fn($a) => $a['confirm_status'] === 'no_answer');
$noPhone    = array_filter($appts, fn($a) => wa_phone($a['phone']) === null);
$notSent    = array_filter($appts, fn($a) => $a['reminder_sent'] === null);

page_header('تأكيد المواعيد', 'reminders.php');
?>
<div class="card">
    <div class="card-head">
        <h2>📞 قائمة الاتصال — <?= e(day_ar($date)) ?> <?= e(fmt_date($date)) ?></h2>
        <form class="inline-form" method="get">
            <a class="btn btn-light btn-sm" href="reminders.php?date=<?= date('Y-m-d') ?>">اليوم</a>
            <a class="btn btn-light btn-sm" href="reminders.php?date=<?= date('Y-m-d', strtotime('+1 day')) ?>">بكرة</a>
            <input type="date" name="date" value="<?= e($date) ?>">
            <button class="btn btn-sm" type="submit">عرض</button>
        </form>
    </div>
    <div class="stats" style="margin-bottom:0">
        <div class="stat accent"><div class="label">مواعيد اليوم</div><div class="value"><?= count($appts) ?></div></div>
        <div class="stat"><div class="label">أكّدوا الحضور</div>
            <div class="value" style="color:#15803d"><?= count($confirmed) ?></div></div>
        <div class="stat"><div class="label">لم يتم التواصل</div>
            <div class="value" style="color:<?= count($noContact) ? '#b45309' : '#15803d' ?>"><?= count($noContact) ?></div></div>
        <?php if ($noAnswer): ?>
        <div class="stat"><div class="label">لم يردوا (أعد المحاولة)</div>
            <div class="value" style="color:#b45309"><?= count($noAnswer) ?></div></div>
        <?php endif; ?>
        <?php if ($declined): ?>
        <div class="stat"><div class="label">اعتذروا</div>
            <div class="value" style="color:#b91c1c"><?= count($declined) ?></div></div>
        <?php endif; ?>
    </div>
    <p class="muted" style="margin-top:12px">
        اتصل بالمريض من زر <strong>📞</strong> أو أرسل له واتساب، ثم سجّل النتيجة.
        الاعتذار يُلغي الموعد تلقائيًا ليصبح الوقت متاحًا لمريض آخر.
    </p>
</div>

<?php if (!$appts && !$declined): ?>
<div class="card"><p class="muted">لا توجد مواعيد محجوزة في هذا اليوم.</p></div>
<?php else: ?>

<?php if ($appts): ?>
<div class="card">
    <div class="card-head">
        <h2>المواعيد المحجوزة</h2>
        <?php if ($notSent): ?>
        <form method="post" data-confirm="تعليم كل المواعيد المتبقية كمُرسَلة بدون فتح واتساب؟">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mark_all">
            <input type="hidden" name="back_date" value="<?= e($date) ?>">
            <button class="btn btn-light btn-sm" type="submit">تعليم الكل كمُرسَل</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المريض</th><th>الطبيب</th><th>الهاتف</th>
            <th>التذكير</th><th>تأكيد الحضور</th><th>الاتصال وتسجيل النتيجة</th></tr></thead>
        <tbody>
        <?php foreach ($appts as $a):
            $phone = wa_phone($a['phone']);
            $tel = tel_link($a['phone']);
            $msg = wa_message($a);
        ?>
            <tr<?= $a['confirm_status'] === 'confirmed' ? ' style="background:#f0fdf4"' : '' ?>>
                <td class="num"><strong><?= e(fmt_time($a['atime'])) ?></strong></td>
                <td><a href="patient.php?id=<?= (int)$a['patient_id'] ?>"><?= e($a['pname']) ?></a>
                    <small class="muted"><?= e($a['code']) ?></small></td>
                <td><?= $a['doctor_name'] ? e($a['doctor_name']) : '<span class="muted">—</span>' ?></td>
                <td class="num" dir="ltr"><?= e($a['phone'] ?: '—') ?></td>
                <td>
                    <?php if ($a['reminder_sent']): ?>
                        <span class="badge ok">أُرسل <?= e(date('d/m', strtotime($a['reminder_sent']))) ?></span>
                    <?php else: ?>
                        <span class="badge mut">لم يُرسل</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge <?= e(CONFIRM_BADGE[$a['confirm_status']]) ?>">
                        <?= e(CONFIRM_STATUS[$a['confirm_status']]) ?></span>
                    <?php if ($a['confirmed_at'] && $a['confirm_status'] !== 'pending'): ?>
                        <br><small class="muted"><?= e($a['confirmed_by_name'] ?? '') ?>
                        · <?= e(date('d/m H:i', strtotime($a['confirmed_at']))) ?></small>
                    <?php endif; ?>
                </td>
                <td><div class="actions">
                    <?php if ($tel): ?>
                        <a class="btn btn-sm" href="<?= e($tel) ?>" title="اتصال بالمريض">📞 اتصال</a>
                    <?php endif; ?>
                    <?php if ($phone): ?>
                        <a class="btn btn-sm btn-wa" href="<?= e(wa_link($phone, $msg)) ?>" target="_blank" rel="noopener"
                           data-aid="<?= (int)$a['id'] ?>" title="واتساب برسالة جاهزة">💬</a>
                        <button class="btn btn-light btn-sm btn-copy" type="button"
                                data-msg="<?= e($msg) ?>" title="نسخ نص الرسالة">نسخ</button>
                    <?php else: ?>
                        <span class="badge bad">رقم غير صالح</span>
                        <a class="btn btn-light btn-sm" href="patients.php?edit=<?= (int)$a['patient_id'] ?>">تعديل الرقم</a>
                    <?php endif; ?>

                    <?php if (can('appt.confirm')): ?>
                        <?php foreach ([
                            'confirmed' => ['✔ أكّد', ''],
                            'no_answer' => ['لم يرد', 'btn-light'],
                            'declined'  => ['اعتذر', 'btn-danger'],
                        ] as $key => [$label, $cls]):
                            if ($a['confirm_status'] === $key) continue; ?>
                            <form method="post"<?= $key === 'declined'
                                ? ' data-confirm="تسجيل اعتذار ' . e($a['pname']) . '؟ سيُلغى الموعد ويصبح الوقت متاحًا."' : '' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="confirm">
                                <input type="hidden" name="back_date" value="<?= e($date) ?>">
                                <input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
                                <input type="hidden" name="confirm" value="<?= e($key) ?>">
                                <button class="btn btn-sm <?= e($cls) ?>" type="submit"><?= e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <form method="post" class="mark-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="<?= $a['reminder_sent'] ? 'unmark' : 'mark' ?>">
                        <input type="hidden" name="back_date" value="<?= e($date) ?>">
                        <input type="hidden" name="aid" value="<?= (int)$a['id'] ?>">
                        <button class="btn btn-light btn-sm" type="submit"><?= $a['reminder_sent'] ? 'تراجع' : 'تم الإرسال' ?></button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<?php if ($declined): ?>
<div class="card">
    <h2>🚫 اعتذروا عن الحضور (<?= count($declined) ?>)</h2>
    <p class="muted">هذه المواعيد أُلغيت وأوقاتها متاحة للحجز.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>الوقت</th><th>المريض</th><th>الهاتف</th><th>سجّلها</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($declined as $a): ?>
            <tr>
                <td class="num"><?= e(fmt_time($a['atime'])) ?></td>
                <td><a href="patient.php?id=<?= (int)$a['patient_id'] ?>"><?= e($a['pname']) ?></a></td>
                <td class="num" dir="ltr"><?= e($a['phone']) ?></td>
                <td><?= e($a['confirmed_by_name'] ?? '—') ?>
                    <small class="muted"><?= $a['confirmed_at'] ? e(date('d/m H:i', strtotime($a['confirmed_at']))) : '' ?></small></td>
                <td><?php if (can('appt.manage')): ?>
                    <a class="btn btn-light btn-sm" href="appointments.php?patient=<?= (int)$a['patient_id'] ?>">حجز موعد جديد</a>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<?php if ($appts): ?>
<div class="card">
    <h2>معاينة نص رسالة واتساب</h2>
    <pre style="background:#f8fafc;border:1px solid var(--line);border-radius:8px;padding:14px;white-space:pre-wrap;font:inherit"><?= e(wa_message($appts[0])) ?></pre>
    <p class="muted">تُعدَّل الصيغة من <a href="settings.php">الإعدادات</a>.</p>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
// فتح واتساب ثم تعليم الموعد كمُرسَل تلقائيًا
document.querySelectorAll('.btn-wa[data-aid]').forEach(function (link) {
    link.addEventListener('click', function () {
        var form = link.closest('.actions').querySelector('.mark-form');
        if (!form || form.querySelector('[name=action]').value !== 'mark') return;
        setTimeout(function () { form.submit(); }, 600);
    });
});

// نسخ نص الرسالة
document.querySelectorAll('.btn-copy').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var msg = btn.dataset.msg;
        var done = function () {
            var old = btn.textContent;
            btn.textContent = 'تم النسخ ✔';
            setTimeout(function () { btn.textContent = old; }, 1600);
        };
        if (navigator.clipboard) {
            navigator.clipboard.writeText(msg).then(done, function () { fallbackCopy(msg, done); });
        } else {
            fallbackCopy(msg, done);
        }
    });
});
function fallbackCopy(text, done) {
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
