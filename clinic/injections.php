<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('inj.view');
require_module('injections');

$tab = $_GET['tab'] ?? 'give';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    /* ------------------------------------------------ تسجيل جرعة حقن */
    if ($action === 'give') {
        deny_unless('inj.give', 'injections.php');
        $pid = posted_patient_id($pdo);
        $drugId = (int)($_POST['drug_id'] ?? 0);
        $units = (float)($_POST['units'] ?? 0);
        $unitPrice = (float)($_POST['unit_price'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0) ?: null;
        $doseDate = ($_POST['dose_date'] ?? '') ?: date('Y-m-d');
        $paidNow = max(0.0, (float)($_POST['paid_now'] ?? 0));

        $st = $pdo->prepare('SELECT * FROM drugs WHERE id = ?');
        $st->execute([$drugId]);
        $drug = $st->fetch();

        if (!$pid || !$drug || $units <= 0) {
            flash('اختر المريض والدواء وأدخل عدد الوحدات.', 'danger');
            redirect('injections.php');
        }

        $amount = round($units * $unitPrice, 2);
        $paidNow = min($paidNow, $amount);

        // البروتوكول النشط للمريض لهذا الدواء (لربط الجرعة به)
        $st = $pdo->prepare("SELECT id FROM injection_plans WHERE patient_id = ? AND drug_id = ? AND status = 'active' ORDER BY id DESC LIMIT 1");
        $st->execute([$pid, $drugId]);
        $planId = $st->fetchColumn() ?: null;

        $site = array_key_exists($_POST['site'] ?? '', INJ_SITES) ? $_POST['site'] : 'abdomen';

        $pdo->beginTransaction();
        try {
            /*
             * قراءة الدفعة وقفلها داخل المعاملة، حتى لا يصرف موظفان في نفس
             * اللحظة وحدات أكثر مما في الدفعة فعليًا.
             */
            $batch = null;
            $unitCost = 0.0;
            if ($batchId) {
                $st = $pdo->prepare('SELECT * FROM drug_batches WHERE id = ? AND drug_id = ? FOR UPDATE');
                $st->execute([$batchId, $drugId]);
                $batch = $st->fetch();
                if (!$batch) {
                    $pdo->rollBack();
                    flash('الدفعة المختارة غير صالحة لهذا الدواء.', 'danger');
                    redirect('injections.php');
                }
                $left = (float)$batch['units_total'] - (float)$batch['units_used'];
                if ($units > $left) {
                    $pdo->rollBack();
                    flash('الدفعة بها ' . units_fmt($left) . ' فقط — اختر دفعة أخرى أو استلم كمية جديدة.', 'danger');
                    redirect('injections.php');
                }
                $unitCost = batch_unit_cost($batch);
            }

            $pdo->prepare(
                'INSERT INTO injection_doses (patient_id, plan_id, drug_id, batch_id, dose_date, units,
                 unit_price, amount, paid, unit_cost, site, notes, given_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $pid, $planId, $drugId, $batchId, $doseDate, $units,
                $unitPrice, $amount, $paidNow, $unitCost, $site,
                trim($_POST['notes'] ?? ''), user()['id'],
            ]);
            $doseId = (int)$pdo->lastInsertId();

            if ($batchId) {
                $pdo->prepare('UPDATE drug_batches SET units_used = units_used + ? WHERE id = ?')
                    ->execute([$units, $batchId]);
            }

            if ($paidNow > 0) {
                $pdo->prepare(
                    'INSERT INTO payments (patient_id, pdate, amount, method, service, notes, created_by, dose_id)
                     VALUES (?,?,?,?,?,?,?,?)'
                )->execute([
                    $pid, $doseDate, $paidNow,
                    array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash',
                    INJ_SERVICE,
                    units_fmt($units) . ' — ' . $drug['name'],
                    user()['id'], $doseId,
                ]);
            }
            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            flash('تعذر تسجيل الجرعة — حاول مرة أخرى.', 'danger');
            redirect('injections.php');
        }

        activity($pdo, 'dose', 'injection', $pid, units_fmt($units) . ' من ' . $drug['name'] . ' بمبلغ ' . money($amount));
        $rest = $amount - $paidNow;
        flash('تم تسجيل ' . units_fmt($units) . ' بمبلغ ' . money($amount)
            . ($rest > 0 ? ' — متبقٍ على المريض ' . money($rest) : ' — مدفوع بالكامل ✔'));
        redirect('patient.php?id=' . $pid . '&tab=inj');
    }

    /* ------------------------------------------------ سداد متأخرات جرعة */
    if ($action === 'pay_dose') {
        deny_unless('pay.create', 'injections.php?tab=due');
        $doseId = (int)($_POST['dose_id'] ?? 0);
        $pay = (float)($_POST['pay'] ?? 0);
        $st = $pdo->prepare('SELECT d.*, dr.name AS drug_name FROM injection_doses d JOIN drugs dr ON dr.id = d.drug_id WHERE d.id = ?');
        $st->execute([$doseId]);
        $dose = $st->fetch();
        if (!$dose || $pay <= 0) {
            flash('أدخل مبلغًا صحيحًا.', 'danger');
            redirect('injections.php?tab=due');
        }
        $due = (float)$dose['amount'] - (float)$dose['paid'];
        $pay = min($pay, $due);

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE injection_doses SET paid = paid + ? WHERE id = ?')->execute([$pay, $doseId]);
            $pdo->prepare(
                'INSERT INTO payments (patient_id, pdate, amount, method, service, notes, created_by, dose_id)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                (int)$dose['patient_id'], date('Y-m-d'), $pay,
                array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash',
                INJ_SERVICE,
                'سداد متأخرات — ' . $dose['drug_name'],
                user()['id'], $doseId,
            ]);
            $pdo->commit();
        } catch (PDOException) {
            $pdo->rollBack();
            flash('تعذر تسجيل السداد.', 'danger');
            redirect('injections.php?tab=due');
        }
        flash('تم سداد ' . money($pay) . '.');
        redirect('injections.php?tab=due');
    }

    /* ------------------------------------------------------ حذف جرعة */
    if ($action === 'del_dose') {
        deny_unless('inj.delete', 'injections.php?tab=log');
        $doseId = (int)($_POST['dose_id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM injection_doses WHERE id = ?');
        $st->execute([$doseId]);
        $dose = $st->fetch();
        if ($dose) {
            $pdo->beginTransaction();
            try {
                if ($dose['batch_id']) {
                    $pdo->prepare('UPDATE drug_batches SET units_used = GREATEST(0, units_used - ?) WHERE id = ?')
                        ->execute([(float)$dose['units'], (int)$dose['batch_id']]);
                }
                // المدفوعات المرتبطة تُحذف تلقائيًا عبر ON DELETE CASCADE
                $pdo->prepare('DELETE FROM injection_doses WHERE id = ?')->execute([$doseId]);
                $pdo->commit();
                activity($pdo, 'delete', 'injection', (int)$dose['patient_id'], 'حذف جرعة ' . units_fmt($dose['units']));
                flash('تم حذف الجرعة وإرجاع الوحدات للمخزن.');
            } catch (PDOException) {
                $pdo->rollBack();
                flash('تعذر حذف الجرعة.', 'danger');
            }
        }
        redirect($_POST['back'] ?? 'injections.php?tab=log');
    }
}

$drugs = $pdo->query('SELECT * FROM drugs WHERE active = 1 ORDER BY name')->fetchAll();
$currency = setting('currency', 'ج.م');

page_header('الحقن', 'injections.php');

$tabs = ['give' => 'تسجيل جرعة', 'due' => 'مستحقة ومتأخرات', 'log' => 'سجل الجرعات'];
?>
<div class="tabs">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="injections.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'give'):
    // بيانات البروتوكولات النشطة لملء النموذج تلقائيًا عند اختيار المريض
    $plans = $pdo->query(
        "SELECT patient_id, drug_id, weekly_units, unit_price FROM injection_plans WHERE status = 'active'"
    )->fetchAll();
    $planMap = [];
    foreach ($plans as $pl) {
        $planMap[(int)$pl['patient_id']] = [
            'drug'  => (int)$pl['drug_id'],
            'units' => (float)$pl['weekly_units'],
            'price' => (float)$pl['unit_price'],
        ];
    }
    $batchMap = [];
    foreach ($drugs as $dr) {
        $batchMap[(int)$dr['id']] = array_map(fn($b) => [
            'id'    => (int)$b['id'],
            'label' => ($b['batch_no'] !== '' ? 'تشغيلة ' . $b['batch_no'] : 'دفعة #' . $b['id'])
                       . ' — متبقٍ ' . num_fmt((float)$b['units_total'] - (float)$b['units_used']) . ' وحدة'
                       . ($b['expiry_date'] ? ' — تنتهي ' . fmt_date($b['expiry_date']) : ''),
        ], drug_batches_available($pdo, (int)$dr['id']));
    }
?>
<div class="card">
    <h2>💉 تسجيل جرعة حقن</h2>
    <?php if (!$drugs): ?>
        <p class="muted">لا توجد أدوية مفعّلة — <a href="drugs.php?new=1">أضف دواءً أولًا</a>.</p>
    <?php else: ?>
    <form method="post" id="dose-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="give">
        <div class="grid4">
            <label>المريض * <?= patient_picker($pdo, 'patient_id') ?></label>
            <label>التاريخ <input type="date" name="dose_date" value="<?= date('Y-m-d') ?>" required></label>
            <label>الدواء *
                <select name="drug_id" id="f-drug" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($drugs as $dr): ?>
                        <option value="<?= (int)$dr['id'] ?>" data-price="<?= e($dr['unit_price']) ?>"><?= e($dr['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>مكان الحقن
                <select name="site">
                    <?php foreach (INJ_SITES as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>

        <h3 class="form-section">المحاسبة بالوحدات</h3>
        <div class="grid4">
            <label>عدد الوحدات * <input type="number" step="0.5" min="0.5" name="units" id="f-units" required></label>
            <label>سعر الوحدة (<?= e($currency) ?>) * <input type="number" step="0.01" min="0" name="unit_price" id="f-price" required></label>
            <label>الإجمالي المستحق
                <input id="f-amount" value="0.00" readonly style="background:#f0fdfa;font-weight:700">
            </label>
            <label>المدفوع الآن <input type="number" step="0.01" min="0" name="paid_now" id="f-paid"></label>
        </div>
        <div class="grid4">
            <label>طريقة الدفع
                <select name="method">
                    <?php foreach (PAY_METHODS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>الخصم من المخزن
                <select name="batch_id" id="f-batch">
                    <option value="">— المريض أحضر الدواء (بدون خصم) —</option>
                </select>
            </label>
            <label style="grid-column:span 2">ملاحظات <input name="notes"></label>
        </div>
        <p class="muted" id="f-hint"></p>
        <button class="btn" type="submit">تسجيل الجرعة</button>
    </form>
    <?php endif; ?>
</div>

<script>
if (document.getElementById('dose-form')) {
var PLANS = <?= json_encode($planMap, JSON_UNESCAPED_UNICODE) ?>;
var BATCHES = <?= json_encode($batchMap, JSON_UNESCAPED_UNICODE) ?>;
var elDrug = document.getElementById('f-drug'), elUnits = document.getElementById('f-units'),
    elPrice = document.getElementById('f-price'), elAmount = document.getElementById('f-amount'),
    elPaid = document.getElementById('f-paid'), elBatch = document.getElementById('f-batch'),
    elHint = document.getElementById('f-hint'), elPatient = document.getElementById('patient_id');

function calc() {
    var total = (parseFloat(elUnits.value) || 0) * (parseFloat(elPrice.value) || 0);
    elAmount.value = total.toFixed(2);
    if (!elPaid.dataset.touched) elPaid.value = total.toFixed(2);
}
function fillBatches() {
    var list = BATCHES[elDrug.value] || [];
    elBatch.innerHTML = '<option value="">— المريض أحضر الدواء (بدون خصم) —</option>';
    list.forEach(function (b) {
        var o = document.createElement('option');
        o.value = b.id;
        o.textContent = b.label;
        elBatch.appendChild(o);
    });
    if (list.length) elBatch.selectedIndex = 1;   // أقرب دفعة انتهاءً
    elHint.textContent = list.length ? '' : 'لا توجد كمية بالمخزن لهذا الدواء — سيُسجَّل بدون خصم.';
}
elUnits.addEventListener('input', calc);
elPrice.addEventListener('input', calc);
elPaid.addEventListener('input', function () { elPaid.dataset.touched = '1'; });
elDrug.addEventListener('change', function () {
    var p = elDrug.options[elDrug.selectedIndex].dataset.price;
    if (p && parseFloat(p) > 0 && !elPrice.value) elPrice.value = p;
    fillBatches();
    calc();
});

// عند اختيار مريض له بروتوكول نشط: املأ الدواء والجرعة والسعر تلقائيًا
document.getElementById('dose-form').addEventListener('patient:selected', function () {
    var plan = PLANS[elPatient.value];
    if (!plan) {
        elHint.textContent = 'لا يوجد بروتوكول نشط لهذا المريض — أدخل البيانات يدويًا.';
        return;
    }
    elDrug.value = plan.drug;
    elUnits.value = plan.units;
    elPrice.value = plan.price;
    fillBatches();
    calc();
    elHint.textContent = 'تم الملء من بروتوكول المريض النشط — يمكنك التعديل.';
});
}
</script>

<?php elseif ($tab === 'due'):
    // البروتوكولات النشطة ومواعيد الجرعات القادمة
    $active = $pdo->query(
        "SELECT pl.*, p.name AS pname, p.phone, p.code, d.name AS drug_name,
            (SELECT MAX(dose_date) FROM injection_doses i WHERE i.plan_id = pl.id) AS last_dose,
            (SELECT COUNT(*) FROM injection_doses i WHERE i.plan_id = pl.id) AS doses_count
         FROM injection_plans pl
         JOIN patients p ON p.id = pl.patient_id
         JOIN drugs d ON d.id = pl.drug_id
         WHERE pl.status = 'active' ORDER BY p.name"
    )->fetchAll();

    $rows = [];
    foreach ($active as $pl) {
        $next = $pl['last_dose'] ? date('Y-m-d', strtotime($pl['last_dose'] . ' +7 days')) : $pl['start_date'];
        $rows[] = $pl + ['next_date' => $next];
    }
    usort($rows, fn($a, $b) => strcmp($a['next_date'], $b['next_date']));

    $debtors = $pdo->query(
        'SELECT p.id, p.name, p.code, p.phone,
            SUM(i.amount - i.paid) AS balance, COUNT(*) AS doses
         FROM injection_doses i JOIN patients p ON p.id = i.patient_id
         GROUP BY p.id, p.name, p.code, p.phone
         HAVING balance > 0.005 ORDER BY balance DESC'
    )->fetchAll();
    $totalDebt = array_sum(array_map(fn($r) => (float)$r['balance'], $debtors));
    $today = date('Y-m-d');
?>
<div class="card">
    <div class="card-head"><h2>📆 الجرعات المستحقة</h2>
        <span class="muted"><?= count($rows) ?> بروتوكول نشط</span></div>
    <div class="table-wrap"><table>
        <thead><tr><th>المريض</th><th>الدواء</th><th>الجرعة الأسبوعية</th><th>سعر الوحدة</th>
            <th>آخر جرعة</th><th>الجرعة القادمة</th><th>عدد الجرعات</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r):
            $overdue = $r['next_date'] < $today;
            $dueToday = $r['next_date'] === $today;
            $wa = wa_phone($r['phone']);
        ?>
            <tr>
                <td><a href="patient.php?id=<?= (int)$r['patient_id'] ?>&tab=inj"><?= e($r['pname']) ?></a>
                    <small class="muted"><?= e($r['code']) ?></small></td>
                <td><?= e($r['drug_name']) ?></td>
                <td class="num"><strong><?= e(num_fmt($r['weekly_units'])) ?></strong> وحدة</td>
                <td class="num"><?= e(money($r['unit_price'])) ?></td>
                <td class="num"><?= e(fmt_date($r['last_dose'])) ?></td>
                <td class="num">
                    <?php if ($overdue): ?><span class="badge bad">متأخرة <?= e(fmt_date($r['next_date'])) ?></span>
                    <?php elseif ($dueToday): ?><span class="badge warn">اليوم</span>
                    <?php else: ?><?= e(fmt_date($r['next_date'])) ?><?php endif; ?>
                </td>
                <td class="num"><?= (int)$r['doses_count'] ?></td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="injections.php?tab=give">تسجيل جرعة</a>
                    <?php if ($wa): ?>
                    <a class="btn btn-sm btn-wa" target="_blank" rel="noopener"
                       href="<?= e(wa_link($wa, 'مرحبًا ' . $r['pname'] . ' 🌿' . "\n"
                            . 'موعد جرعة ' . $r['drug_name'] . ' يوم ' . day_ar($r['next_date']) . ' الموافق ' . fmt_date($r['next_date']) . '.' . "\n"
                            . 'الجرعة: ' . num_fmt($r['weekly_units']) . ' وحدة.' . "\n"
                            . 'برجاء تأكيد الحضور — ' . setting('clinic_name', 'العيادة'))) ?>">💬</a>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted">لا توجد بروتوكولات نشطة. ابدأ بروتوكولًا من ملف المريض ← تبويب الحقن.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>💳 متأخرات الحقن</h2>
        <span class="badge <?= $totalDebt > 0 ? 'bad' : 'ok' ?>">الإجمالي: <?= e(money($totalDebt)) ?></span>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>المريض</th><th>الكود</th><th>عدد الجرعات</th><th>المتأخر</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($debtors as $r): ?>
            <tr>
                <td><a href="patient.php?id=<?= (int)$r['id'] ?>&tab=inj"><?= e($r['name']) ?></a></td>
                <td class="num"><?= e($r['code']) ?></td>
                <td class="num"><?= (int)$r['doses'] ?></td>
                <td class="num"><strong style="color:#b91c1c"><?= e(money($r['balance'])) ?></strong></td>
                <td><a class="btn btn-light btn-sm" href="patient.php?id=<?= (int)$r['id'] ?>&tab=inj">كشف الحساب</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$debtors): ?><tr><td colspan="5" class="muted">لا توجد متأخرات — كل الجرعات مسدَّدة ✔</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php else:
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    $ok = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
    if (!$ok($from)) $from = date('Y-m-01');
    if (!$ok($to)) $to = date('Y-m-d');

    $st = $pdo->prepare(
        'SELECT i.*, p.name AS pname, p.code, d.name AS drug_name, u.name AS uname
         FROM injection_doses i
         JOIN patients p ON p.id = i.patient_id
         JOIN drugs d ON d.id = i.drug_id
         LEFT JOIN users u ON u.id = i.given_by
         WHERE i.dose_date BETWEEN ? AND ? ORDER BY i.dose_date DESC, i.id DESC'
    );
    $st->execute([$from, $to]);
    $doses = $st->fetchAll();
    $sumUnits = array_sum(array_map(fn($r) => (float)$r['units'], $doses));
    $sumAmount = array_sum(array_map(fn($r) => (float)$r['amount'], $doses));
    $sumPaid = array_sum(array_map(fn($r) => (float)$r['paid'], $doses));
    $sumProfit = array_sum(array_map(fn($r) => (float)$r['units'] * ((float)$r['unit_price'] - (float)$r['unit_cost']), $doses));
?>
<div class="stats">
    <div class="stat accent"><div class="label">إجمالي الوحدات</div><div class="value"><?= e(num_fmt($sumUnits)) ?></div></div>
    <div class="stat"><div class="label">إجمالي المستحق</div><div class="value"><?= e(money($sumAmount)) ?></div></div>
    <div class="stat"><div class="label">المحصَّل</div><div class="value"><?= e(money($sumPaid)) ?></div></div>
    <div class="stat"><div class="label">المتأخر</div>
        <div class="value" style="color:<?= $sumAmount - $sumPaid > 0 ? '#b91c1c' : '#15803d' ?>"><?= e(money($sumAmount - $sumPaid)) ?></div></div>
    <?php if (can('profit.view')): ?>
    <div class="stat"><div class="label">ربح الحقن</div><div class="value" style="color:#15803d"><?= e(money($sumProfit)) ?></div></div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h2>📋 سجل الجرعات</h2>
        <form class="inline-form" method="get">
            <input type="hidden" name="tab" value="log">
            <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="btn btn-light btn-sm" type="submit">عرض</button>
            <a class="btn btn-xls btn-sm" href="export.php?type=injections&from=<?= e($from) ?>&to=<?= e($to) ?>">⬇ تصدير Excel</a>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>المريض</th><th>الدواء</th><th>الوحدات</th><th>سعر الوحدة</th>
            <th>المستحق</th><th>المدفوع</th><th>المتبقي</th><th>أعطاها</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($doses as $r): $rest = (float)$r['amount'] - (float)$r['paid']; ?>
            <tr>
                <td class="num"><?= e(fmt_date($r['dose_date'])) ?></td>
                <td><a href="patient.php?id=<?= (int)$r['patient_id'] ?>&tab=inj"><?= e($r['pname']) ?></a></td>
                <td><?= e($r['drug_name']) ?></td>
                <td class="num"><strong><?= e(num_fmt($r['units'])) ?></strong></td>
                <td class="num"><?= e(money($r['unit_price'])) ?></td>
                <td class="num"><?= e(money($r['amount'])) ?></td>
                <td class="num"><?= e(money($r['paid'])) ?></td>
                <td class="num"><?= $rest > 0.005
                    ? '<span class="badge bad">' . e(money($rest)) . '</span>'
                    : '<span class="badge ok">مسدَّد</span>' ?></td>
                <td><?= e($r['uname'] ?? '—') ?></td>
                <td>
                <?php if (can('inj.delete')): ?>
                    <form method="post" data-confirm="حذف الجرعة؟ سيتم إرجاع الوحدات للمخزن وحذف مدفوعاتها.">
                        <?= csrf_field() ?><input type="hidden" name="action" value="del_dose">
                        <input type="hidden" name="dose_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="back" value="injections.php?tab=log&from=<?= e($from) ?>&to=<?= e($to) ?>">
                        <button class="btn btn-light btn-sm" type="submit">حذف</button>
                    </form>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$doses): ?><tr><td colspan="10" class="muted">لا توجد جرعات في هذه الفترة.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr>
            <td colspan="3">الإجمالي (<?= count($doses) ?> جرعة)</td>
            <td class="num"><?= e(num_fmt($sumUnits)) ?></td>
            <td></td>
            <td class="num"><?= e(money($sumAmount)) ?></td>
            <td class="num"><?= e(money($sumPaid)) ?></td>
            <td class="num"><?= e(money($sumAmount - $sumPaid)) ?></td>
            <td colspan="2"></td>
        </tr></tfoot>
    </table></div>
</div>
<?php endif;
page_footer();
