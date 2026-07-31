<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('queue.view');

$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    $date = date('Y-m-d');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    $back = 'queue.php?date=' . urlencode($_POST['back_date'] ?? $date);

    if ($action === 'add') {
        deny_unless('queue.manage', $back);
        $pid = posted_patient_id($pdo);
        if (!$pid) {
            flash('اختر المريض من قائمة البحث.', 'danger');
            redirect($back);
        }
        $st = $pdo->prepare("SELECT id FROM queue WHERE qdate = ? AND patient_id = ? AND status IN ('waiting','in_room')");
        $st->execute([$date, $pid]);
        if ($st->fetch()) {
            flash('المريض موجود في دور اليوم بالفعل.', 'danger');
            redirect($back);
        }

        $docId = ($_POST['doctor_id'] ?? '') !== '' ? (int)$_POST['doctor_id'] : null;
        if (!$docId) {
            $q = $pdo->prepare('SELECT doctor_id FROM patients WHERE id = ?');
            $q->execute([$pid]);
            $docId = $q->fetchColumn() ?: null;
        }
        // ربط الدور بموعد اليوم إن وُجد
        $q = $pdo->prepare("SELECT id FROM appointments WHERE patient_id = ? AND adate = ? AND status = 'scheduled' ORDER BY atime LIMIT 1");
        $q->execute([$pid, $date]);
        $apptId = $q->fetchColumn() ?: null;

        /*
         * الرقم يُحسب ويُدرج داخل معاملة مع قفل، لأن موظفَين قد يسجّلان وصول
         * مريضين في نفس اللحظة فيحصلان على نفس الرقم. مفتاح فريد (اليوم، الرقم)
         * يمنع التكرار نهائيًا حتى لو تجاوز القفل.
         */
        for ($try = 0; $try < 5; $try++) {
            try {
                $pdo->beginTransaction();
                $num = queue_next_number($pdo, $date);
                $pdo->prepare(
                    'INSERT INTO queue (qdate, number, patient_id, doctor_id, appointment_id, notes, created_by)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([$date, $num, $pid, $docId, $apptId, trim($_POST['notes'] ?? ''), user()['id']]);
                $pdo->commit();

                $q = $pdo->prepare('SELECT name FROM patients WHERE id = ?');
                $q->execute([$pid]);
                activity($pdo, 'queue', 'queue', $pid, 'دخول الدور رقم ' . $num . ' — ' . (string)$q->fetchColumn());
                flash('تم تسجيل الوصول برقم ' . $num . '.');
                redirect($back);
            } catch (PDOException) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                usleep(50000);   // رقم مُستهلك — أعد المحاولة برقم جديد
            }
        }
        flash('تعذر تسجيل الوصول — حاول مرة أخرى.', 'danger');
        redirect($back);
    }

    if ($action === 'status') {
        deny_unless('queue.manage', $back);
        $qid = (int)($_POST['qid'] ?? 0);
        $status = array_key_exists($_POST['status'] ?? '', QUEUE_STATUS) ? $_POST['status'] : 'waiting';

        $stamp = match ($status) {
            'in_room' => ', called_at = NOW()',
            'done', 'skipped' => ', done_at = NOW()',
            default => '',
        };
        $pdo->prepare("UPDATE queue SET status = ?$stamp WHERE id = ?")->execute([$status, $qid]);

        // إنهاء الدور يُعلّم الموعد المرتبط كـ«تم» تلقائيًا
        if ($status === 'done') {
            $st = $pdo->prepare('SELECT appointment_id, patient_id FROM queue WHERE id = ?');
            $st->execute([$qid]);
            if ($row = $st->fetch()) {
                if ($row['appointment_id'] && can('appt.manage')) {
                    $pdo->prepare("UPDATE appointments SET status='done' WHERE id=? AND status='scheduled'")
                        ->execute([(int)$row['appointment_id']]);
                }
                activity($pdo, 'queue', 'queue', (int)$row['patient_id'], 'إنهاء الكشف من الدور');
            }
        }
        redirect($back);
    }

    if ($action === 'remove') {
        deny_unless('queue.manage', $back);
        $pdo->prepare('DELETE FROM queue WHERE id = ?')->execute([(int)$_POST['qid']]);
        flash('تم حذف السجل من الدور.');
        redirect($back);
    }
}

$rows = queue_snapshot($pdo, $date);
$waiting = array_values(array_filter($rows, fn($r) => $r['status'] === 'waiting'));
$inRoom  = array_values(array_filter($rows, fn($r) => $r['status'] === 'in_room'));
$done    = array_filter($rows, fn($r) => $r['status'] === 'done');
$doctors = doctors_list($pdo);

/* ------------------------------------ شاشة العرض لغرفة الانتظار */
if (isset($_GET['screen'])) {
    $current = $inRoom[0] ?? null;
    $next = array_slice($waiting, 0, 4);
    ?>
    <!DOCTYPE html>
    <html lang="ar" dir="rtl">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="15">
    <title>شاشة الانتظار — <?= e(setting('clinic_name', 'العيادة')) ?></title>
    <link rel="stylesheet" href="assets/cairo.css">
    <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Cairo','Segoe UI',Tahoma,Arial,sans-serif;background:#0f172a;color:#fff;
         min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:3vh 3vw}
    h1{font-size:clamp(20px,3vw,34px);color:#5eead4;margin-bottom:3vh}
    .now{background:#0f766e;border-radius:24px;padding:4vh 6vw;text-align:center;margin-bottom:4vh;
         box-shadow:0 20px 60px rgba(15,118,110,.45);min-width:min(90vw,520px)}
    .now .lbl{font-size:clamp(15px,2vw,22px);opacity:.85;margin-bottom:1vh}
    .now .num{font-size:clamp(70px,18vw,190px);font-weight:700;line-height:1;font-variant-numeric:tabular-nums}
    .now .who{font-size:clamp(17px,2.4vw,28px);margin-top:1.5vh}
    .idle{background:#1e293b;color:#94a3b8}
    .next{display:flex;gap:2vw;flex-wrap:wrap;justify-content:center}
    .chip{background:#1e293b;border:2px solid #334155;border-radius:16px;padding:2vh 3vw;text-align:center;min-width:110px}
    .chip .n{font-size:clamp(26px,5vw,52px);font-weight:700;font-variant-numeric:tabular-nums}
    .chip .t{font-size:13px;color:#94a3b8}
    .foot{margin-top:4vh;color:#64748b;font-size:14px}
    </style>
    </head>
    <body>
        <h1>🍏 <?= e(setting('clinic_name', 'العيادة')) ?></h1>
        <?php if ($current): ?>
            <div class="now">
                <div class="lbl">الدور الحالي</div>
                <div class="num"><?= (int)$current['number'] ?></div>
                <div class="who"><?= e($current['pname']) ?><?= $current['doctor_name'] ? ' — ' . e($current['doctor_name']) : '' ?></div>
            </div>
        <?php else: ?>
            <div class="now idle">
                <div class="lbl">لا يوجد كشف جارٍ حاليًا</div>
                <div class="num">—</div>
            </div>
        <?php endif; ?>

        <?php if ($next): ?>
        <div class="next">
            <?php foreach ($next as $n): ?>
                <div class="chip"><div class="n"><?= (int)$n['number'] ?></div><div class="t">في الانتظار</div></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="foot">عدد المنتظرين: <?= count($waiting) ?> · تُحدَّث الشاشة تلقائيًا</div>
    </body>
    </html>
    <?php
    exit;
}

page_header('الدور والانتظار', 'queue.php');
$avgWait = null;
if ($done) {
    $mins = [];
    foreach ($done as $d) {
        if ($d['called_at']) {
            $mins[] = (strtotime($d['called_at']) - strtotime($d['arrived_at'])) / 60;
        }
    }
    $avgWait = $mins ? round(array_sum($mins) / count($mins)) : null;
}
?>
<div class="stats">
    <div class="stat accent"><div class="label">في الانتظار</div><div class="value"><?= count($waiting) ?></div></div>
    <div class="stat"><div class="label">بالداخل الآن</div>
        <div class="value"><?= $inRoom ? '#' . (int)$inRoom[0]['number'] : '—' ?></div></div>
    <div class="stat"><div class="label">انتهوا اليوم</div><div class="value"><?= count($done) ?></div></div>
    <?php if ($avgWait !== null): ?>
    <div class="stat"><div class="label">متوسط الانتظار</div><div class="value"><?= $avgWait ?> <small style="font-size:14px;font-weight:400">دقيقة</small></div></div>
    <?php endif; ?>
</div>

<?php if (can('queue.manage')): ?>
<div class="card">
    <div class="card-head">
        <h2>🔢 تسجيل وصول مريض</h2>
        <div class="actions">
            <a class="btn btn-light btn-sm" href="queue.php?date=<?= e($date) ?>&screen=1" target="_blank">📺 شاشة الانتظار</a>
            <form class="inline-form" method="get">
                <input type="date" name="date" value="<?= e($date) ?>">
                <button class="btn btn-light btn-sm" type="submit">يوم آخر</button>
            </form>
        </div>
    </div>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="back_date" value="<?= e($date) ?>">
        <div class="grid3">
            <label>المريض * <?= patient_picker($pdo) ?></label>
            <label>الطبيب
                <select name="doctor_id">
                    <option value="">— طبيب المريض المعالج —</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?= (int)$doc['id'] ?>"><?= e($doc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>ملاحظات <input name="notes"></label>
        </div>
        <button class="btn" type="submit">تسجيل الوصول وإعطاء رقم</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>دور <?= e(day_ar($date)) ?> <?= e(fmt_date($date)) ?></h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الرقم</th><th>المريض</th><th>الطبيب</th><th>الوصول</th>
            <th>الانتظار</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $endRef = $r['called_at'] ?: ($r['status'] === 'waiting' ? date('Y-m-d H:i:s') : $r['done_at']);
            $waitMin = $endRef ? max(0, (int)round((strtotime($endRef) - strtotime($r['arrived_at'])) / 60)) : null;
            $wa = wa_phone($r['phone']);
        ?>
            <tr<?= $r['status'] === 'in_room' ? ' style="background:#f0fdfa"' : '' ?>>
                <td class="num"><strong style="font-size:17px">#<?= (int)$r['number'] ?></strong></td>
                <td><a href="patient.php?id=<?= (int)$r['patient_id'] ?>"><?= e($r['pname']) ?></a>
                    <small class="muted"><?= e($r['code']) ?></small>
                    <?php if ($r['notes']): ?><br><small class="muted"><?= e($r['notes']) ?></small><?php endif; ?></td>
                <td><?= $r['doctor_name'] ? e($r['doctor_name']) : '<span class="muted">—</span>' ?></td>
                <td class="num"><?= e(date('g:i', strtotime($r['arrived_at']))) ?><?= date('a', strtotime($r['arrived_at'])) === 'am' ? ' ص' : ' م' ?></td>
                <td class="num"><?= $waitMin !== null ? $waitMin . ' د' : '—' ?></td>
                <td><span class="badge <?= e(QUEUE_BADGE[$r['status']]) ?>"><?= e(QUEUE_STATUS[$r['status']]) ?></span></td>
                <td><div class="actions">
                    <?php if (can('queue.manage')): ?>
                        <?php
                        $btns = match ($r['status']) {
                            'waiting' => ['in_room' => '📢 نداء', 'skipped' => 'تخطّي'],
                            'in_room' => ['done' => '✔ إنهاء', 'waiting' => 'إرجاع للانتظار'],
                            default   => ['waiting' => 'إعادة للانتظار'],
                        };
                        foreach ($btns as $s => $label): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="status">
                                <input type="hidden" name="back_date" value="<?= e($date) ?>">
                                <input type="hidden" name="qid" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="status" value="<?= e($s) ?>">
                                <button class="btn <?= $s === 'in_room' || $s === 'done' ? '' : 'btn-light' ?> btn-sm" type="submit"><?= e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                        <?php if ($wa && $r['status'] === 'waiting'): ?>
                            <a class="btn btn-sm btn-wa" target="_blank" rel="noopener"
                               href="<?= e(wa_link($wa, 'دورك اقترب في ' . setting('clinic_name', 'العيادة') . ' — رقمك ' . (int)$r['number'] . '. برجاء التواجد بالعيادة.')) ?>">💬</a>
                        <?php endif; ?>
                        <form method="post" data-confirm="حذف السجل من دور اليوم؟">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="back_date" value="<?= e($date) ?>">
                            <input type="hidden" name="qid" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit">حذف</button>
                        </form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="7" class="muted">لا يوجد أحد في دور هذا اليوم.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
