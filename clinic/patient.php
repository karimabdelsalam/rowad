<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    flash('المريض غير موجود.', 'danger');
    redirect('patients.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_measure') {
        $weight = (float)($_POST['weight'] ?? 0);
        if ($weight <= 0) {
            flash('الوزن مطلوب.', 'danger');
            redirect("patient.php?id=$id&tab=measure");
        }
        $opt = fn(string $k) => ($_POST[$k] ?? '') !== '' ? (float)$_POST[$k] : null;
        $st = $pdo->prepare(
            'INSERT INTO measurements (patient_id, mdate, weight, body_fat, muscle, water, waist, hips, arm, thigh, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([
            $id,
            ($_POST['mdate'] ?? '') ?: date('Y-m-d'),
            $weight,
            $opt('body_fat'), $opt('muscle'), $opt('water'),
            $opt('waist'), $opt('hips'), $opt('arm'), $opt('thigh'),
            trim($_POST['m_notes'] ?? ''),
            user()['id'],
        ]);
        flash('تم تسجيل القياس.');
        redirect("patient.php?id=$id&tab=measure");
    }

    if ($action === 'del_measure' && has_role('admin', 'doctor')) {
        $pdo->prepare('DELETE FROM measurements WHERE id = ? AND patient_id = ?')
            ->execute([(int)$_POST['mid'], $id]);
        flash('تم حذف القياس.');
        redirect("patient.php?id=$id&tab=measure");
    }

    /* ------------------------------------------- بروتوكول الحقن للمريض */
    if ($action === 'save_plan' && has_role('admin', 'doctor')) {
        $planId = (int)($_POST['plan_id'] ?? 0);
        $drugId = (int)($_POST['drug_id'] ?? 0);
        $weekly = (float)($_POST['weekly_units'] ?? 0);
        $price = (float)($_POST['unit_price'] ?? 0);
        if (!$drugId || $weekly <= 0) {
            flash('اختر الدواء وأدخل الجرعة الأسبوعية بالوحدات.', 'danger');
            redirect("patient.php?id=$id&tab=inj");
        }
        $status = array_key_exists($_POST['status'] ?? '', INJ_STATUS) ? $_POST['status'] : 'active';
        $data = [
            $drugId,
            ($_POST['start_date'] ?? '') ?: date('Y-m-d'),
            ($_POST['end_date'] ?? '') ?: null,
            $weekly, $price, $status,
            trim($_POST['plan_notes'] ?? ''),
        ];
        if ($planId) {
            $pdo->prepare('UPDATE injection_plans SET drug_id=?, start_date=?, end_date=?, weekly_units=?,
                           unit_price=?, status=?, notes=? WHERE id=? AND patient_id=?')
                ->execute([...$data, $planId, $id]);
            flash('تم تحديث البروتوكول.');
        } else {
            // بروتوكول واحد نشط في كل وقت — أوقف السابق تلقائيًا
            if ($status === 'active') {
                $pdo->prepare("UPDATE injection_plans SET status='completed' WHERE patient_id=? AND status='active'")
                    ->execute([$id]);
            }
            $pdo->prepare('INSERT INTO injection_plans (patient_id, drug_id, start_date, end_date, weekly_units,
                           unit_price, status, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([$id, ...$data, user()['id']]);
            flash('تم إنشاء بروتوكول الحقن.');
        }
        redirect("patient.php?id=$id&tab=inj");
    }

    if ($action === 'pay_inj' && has_role('admin', 'reception')) {
        $doseId = (int)($_POST['dose_id'] ?? 0);
        $pay = (float)($_POST['pay'] ?? 0);
        $st = $pdo->prepare('SELECT d.*, dr.name AS drug_name FROM injection_doses d
                             JOIN drugs dr ON dr.id = d.drug_id WHERE d.id = ? AND d.patient_id = ?');
        $st->execute([$doseId, $id]);
        $dose = $st->fetch();
        if ($dose && $pay > 0) {
            $pay = min($pay, (float)$dose['amount'] - (float)$dose['paid']);
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE injection_doses SET paid = paid + ? WHERE id = ?')->execute([$pay, $doseId]);
                $pdo->prepare('INSERT INTO payments (patient_id, pdate, amount, method, service, notes, created_by, dose_id)
                               VALUES (?,?,?,?,?,?,?,?)')
                    ->execute([
                        $id, date('Y-m-d'), $pay,
                        array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash',
                        INJ_SERVICE, 'سداد متأخرات — ' . $dose['drug_name'], user()['id'], $doseId,
                    ]);
                $pdo->commit();
                flash('تم سداد ' . money($pay) . '.');
            } catch (PDOException) {
                $pdo->rollBack();
                flash('تعذر تسجيل السداد.', 'danger');
            }
        }
        redirect("patient.php?id=$id&tab=inj");
    }

    if ($action === 'add_payment' && has_role('admin', 'reception')) {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            flash('أدخل مبلغًا صحيحًا.', 'danger');
            redirect("patient.php?id=$id&tab=pay");
        }
        $method = array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash';
        $st = $pdo->prepare(
            'INSERT INTO payments (patient_id, pdate, amount, method, service, notes, created_by) VALUES (?,?,?,?,?,?,?)'
        );
        $st->execute([
            $id,
            ($_POST['pdate'] ?? '') ?: date('Y-m-d'),
            $amount,
            $method,
            trim($_POST['service'] ?? ''),
            trim($_POST['p_notes'] ?? ''),
            user()['id'],
        ]);
        flash('تم تسجيل الدفعة.');
        redirect("patient.php?id=$id&tab=pay");
    }
}

$tab = $_GET['tab'] ?? 'overview';
$age = calc_age($p['birth_date']);

$st = $pdo->prepare('SELECT * FROM measurements WHERE patient_id = ? ORDER BY mdate, id');
$st->execute([$id]);
$measures = $st->fetchAll();
$last = $measures ? end($measures) : null;
$first = $measures[0] ?? null;

page_header('ملف: ' . $p['name'], 'patients.php');

$injBalance = injection_balance($pdo, $id);

$tabs = [
    'overview' => 'نظرة عامة',
    'measure'  => 'القياسات (' . count($measures) . ')',
    'plans'    => 'الأنظمة الغذائية',
    'inj'      => 'الحقن' . ($injBalance > 0.005 ? ' ⚠' : ''),
    'appts'    => 'المواعيد',
];
if (has_role('admin', 'reception')) {
    $tabs['pay'] = 'المدفوعات';
}
?>
<div class="card">
    <div class="card-head">
        <h2><?= e($p['name']) ?> <small class="muted">(<?= e($p['code']) ?>)</small></h2>
        <div class="actions">
            <?php $waPhone = wa_phone($p['phone']); if ($waPhone): ?>
                <a class="btn btn-sm btn-wa" target="_blank" rel="noopener"
                   href="<?= e('https://wa.me/' . $waPhone) ?>" title="فتح محادثة واتساب">💬 واتساب</a>
            <?php endif; ?>
            <a class="btn btn-light btn-sm" href="patients.php?edit=<?= $id ?>">تعديل البيانات</a>
            <a class="btn btn-sm" href="plan_edit.php?patient=<?= $id ?>">+ نظام غذائي</a>
            <?php if (has_role('admin')): ?>
            <form method="post" action="patients.php" data-confirm="سيتم حذف المريض وكل قياساته ومواعيده وأنظمته نهائيًا. متأكد؟">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
                <button class="btn btn-danger btn-sm" type="submit">حذف</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="grid4">
        <p><span class="muted">الهاتف:</span> <span dir="ltr"><?= e($p['phone'] ?: '—') ?></span></p>
        <p><span class="muted">النوع:</span> <?= $p['gender'] === 'male' ? 'ذكر' : 'أنثى' ?></p>
        <p><span class="muted">العمر:</span> <?= $age !== null ? $age . ' سنة' : '—' ?></p>
        <p><span class="muted">الطول:</span> <?= $p['height_cm'] ? e($p['height_cm']) . ' سم' : '—' ?></p>
    </div>
    <?php if ($p['goal']): ?><p><span class="muted">الهدف:</span> <strong><?= e($p['goal']) ?></strong></p><?php endif; ?>
    <?php if ($p['medical_conditions']): ?><p><span class="muted">حالة طبية:</span> <span class="badge warn"><?= e($p['medical_conditions']) ?></span></p><?php endif; ?>
    <?php if ($p['allergies']): ?><p><span class="muted">حساسية:</span> <span class="badge bad"><?= e($p['allergies']) ?></span></p><?php endif; ?>
</div>

<div class="tabs">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="patient.php?id=<?= $id ?>&tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
    <div class="stats">
        <?php if ($last): ?>
            <div class="stat accent"><div class="label">الوزن الحالي</div><div class="value"><?= e($last['weight']) ?> كجم</div></div>
            <?php if ($first && $first !== $last):
                $diff = round((float)$last['weight'] - (float)$first['weight'], 1); ?>
                <div class="stat"><div class="label">التغير منذ البداية</div>
                    <div class="value" style="color:<?= $diff <= 0 ? '#15803d' : '#b91c1c' ?>"><?= ($diff > 0 ? '+' : '') . $diff ?> كجم</div></div>
            <?php endif; ?>
            <?php $bmi = calc_bmi($last['weight'], $p['height_cm']); if ($bmi !== null): [$bl, $bc] = bmi_label($bmi); ?>
                <div class="stat"><div class="label">مؤشر كتلة الجسم BMI</div>
                    <div class="value"><?= e((string)$bmi) ?> <span class="badge <?= e($bc) ?>"><?= e($bl) ?></span></div></div>
            <?php endif; ?>
            <?php if ($last['body_fat'] !== null): ?>
                <div class="stat"><div class="label">نسبة الدهون</div><div class="value"><?= e($last['body_fat']) ?>%</div></div>
            <?php endif; ?>
        <?php else: ?>
            <div class="stat"><div class="label">لا توجد قياسات بعد</div>
                <div class="value"><a href="patient.php?id=<?= $id ?>&tab=measure" style="font-size:15px">سجّل أول قياس ←</a></div></div>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>📉 منحنى تطور الوزن</h2>
        <?= weight_chart_svg($measures) ?>
    </div>

<?php elseif ($tab === 'measure'): ?>
    <div class="card">
        <h2>تسجيل قياس جديد</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_measure">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="grid4">
                <label>التاريخ <input type="date" name="mdate" value="<?= date('Y-m-d') ?>" required></label>
                <label>الوزن (كجم) * <input type="number" step="0.1" min="1" name="weight" required></label>
                <label>الدهون % <input type="number" step="0.1" min="0" max="80" name="body_fat"></label>
                <label>العضلات (كجم) <input type="number" step="0.1" min="0" name="muscle"></label>
                <label>الماء % <input type="number" step="0.1" min="0" max="90" name="water"></label>
                <label>الوسط (سم) <input type="number" step="0.1" min="0" name="waist"></label>
                <label>الأرداف (سم) <input type="number" step="0.1" min="0" name="hips"></label>
                <label>الذراع (سم) <input type="number" step="0.1" min="0" name="arm"></label>
                <label>الفخذ (سم) <input type="number" step="0.1" min="0" name="thigh"></label>
                <label>ملاحظات <input name="m_notes"></label>
            </div>
            <button class="btn" type="submit">حفظ القياس</button>
        </form>
    </div>
    <div class="card">
        <div class="card-head">
            <h2>سجل القياسات</h2>
            <?php if ($measures): ?>
            <a class="btn btn-xls btn-sm" href="export.php?type=measurements&patient=<?= $id ?>">⬇ تصدير Excel</a>
            <?php endif; ?>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>التاريخ</th><th>الوزن</th><th>BMI</th><th>دهون %</th><th>عضلات</th><th>وسط</th><th>أرداف</th><th>ذراع</th><th>فخذ</th><th>ملاحظات</th><th></th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($measures) as $m): $bmi = calc_bmi($m['weight'], $p['height_cm']); ?>
                <tr>
                    <td class="num"><?= e(fmt_date($m['mdate'])) ?></td>
                    <td class="num"><strong><?= e($m['weight']) ?></strong></td>
                    <td class="num"><?= $bmi !== null ? e((string)$bmi) : '—' ?></td>
                    <td class="num"><?= $m['body_fat'] !== null ? e($m['body_fat']) : '—' ?></td>
                    <td class="num"><?= $m['muscle'] !== null ? e($m['muscle']) : '—' ?></td>
                    <td class="num"><?= $m['waist'] !== null ? e($m['waist']) : '—' ?></td>
                    <td class="num"><?= $m['hips'] !== null ? e($m['hips']) : '—' ?></td>
                    <td class="num"><?= $m['arm'] !== null ? e($m['arm']) : '—' ?></td>
                    <td class="num"><?= $m['thigh'] !== null ? e($m['thigh']) : '—' ?></td>
                    <td><?= e($m['notes']) ?></td>
                    <td>
                    <?php if (has_role('admin', 'doctor')): ?>
                        <form method="post" data-confirm="حذف هذا القياس؟">
                            <?= csrf_field() ?><input type="hidden" name="action" value="del_measure">
                            <input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="mid" value="<?= (int)$m['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit">حذف</button>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$measures): ?><tr><td colspan="11" class="muted">لا توجد قياسات.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<?php elseif ($tab === 'plans'):
    $st = $pdo->prepare('SELECT d.*, u.name AS uname FROM diet_plans d LEFT JOIN users u ON u.id = d.created_by WHERE d.patient_id = ? ORDER BY d.start_date DESC, d.id DESC');
    $st->execute([$id]);
    $plans = $st->fetchAll();
?>
    <div class="card">
        <div class="card-head">
            <h2>الأنظمة الغذائية</h2>
            <a class="btn" href="plan_edit.php?patient=<?= $id ?>">+ نظام غذائي جديد</a>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>العنوان</th><th>من</th><th>إلى</th><th>السعرات</th><th>أنشأه</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($plans as $pl): ?>
                <tr>
                    <td><strong><?= e($pl['title']) ?></strong></td>
                    <td class="num"><?= e(fmt_date($pl['start_date'])) ?></td>
                    <td class="num"><?= e(fmt_date($pl['end_date'])) ?></td>
                    <td class="num"><?= $pl['calories'] ? e($pl['calories']) . ' سعر' : '—' ?></td>
                    <td><?= e($pl['uname'] ?? '—') ?></td>
                    <td><div class="actions">
                        <a class="btn btn-sm" href="plan_print.php?id=<?= (int)$pl['id'] ?>">🖨️ طباعة</a>
                        <a class="btn btn-light btn-sm" href="plan_edit.php?id=<?= (int)$pl['id'] ?>">تعديل</a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$plans): ?><tr><td colspan="6" class="muted">لا توجد أنظمة غذائية لهذا المريض.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<?php elseif ($tab === 'inj'):
    $drugList = $pdo->query('SELECT * FROM drugs WHERE active = 1 ORDER BY name')->fetchAll();

    $st = $pdo->prepare(
        'SELECT pl.*, d.name AS drug_name FROM injection_plans pl
         JOIN drugs d ON d.id = pl.drug_id WHERE pl.patient_id = ?
         ORDER BY (pl.status = "active") DESC, pl.start_date DESC, pl.id DESC'
    );
    $st->execute([$id]);
    $plansInj = $st->fetchAll();
    $current = null;
    foreach ($plansInj as $pl) {
        if ($pl['status'] === 'active') { $current = $pl; break; }
    }

    $st = $pdo->prepare(
        'SELECT i.*, d.name AS drug_name, u.name AS uname FROM injection_doses i
         JOIN drugs d ON d.id = i.drug_id LEFT JOIN users u ON u.id = i.given_by
         WHERE i.patient_id = ? ORDER BY i.dose_date, i.id'
    );
    $st->execute([$id]);
    $doses = $st->fetchAll();

    $totUnits = array_sum(array_map(fn($r) => (float)$r['units'], $doses));
    $totAmount = array_sum(array_map(fn($r) => (float)$r['amount'], $doses));
    $totPaid = array_sum(array_map(fn($r) => (float)$r['paid'], $doses));

    $editPlanId = (int)($_GET['plan'] ?? 0);
    $editPlan = null;
    foreach ($plansInj as $pl) {
        if ((int)$pl['id'] === $editPlanId) { $editPlan = $pl; break; }
    }
    $showPlanForm = $editPlan || isset($_GET['newplan']) || !$plansInj;
?>
    <div class="stats">
        <div class="stat accent"><div class="label">إجمالي الوحدات المأخوذة</div>
            <div class="value"><?= e(num_fmt($totUnits)) ?></div></div>
        <div class="stat"><div class="label">إجمالي المستحق</div><div class="value"><?= e(money($totAmount)) ?></div></div>
        <div class="stat"><div class="label">المسدَّد</div><div class="value"><?= e(money($totPaid)) ?></div></div>
        <div class="stat"><div class="label">الرصيد المتبقي</div>
            <div class="value" style="color:<?= $injBalance > 0.005 ? '#b91c1c' : '#15803d' ?>">
                <?= e(money($injBalance)) ?></div></div>
    </div>

    <?php if ($current):
        $nextDate = next_dose_date($pdo, $current);
        $overdue = $nextDate < date('Y-m-d');
    ?>
    <div class="card">
        <div class="card-head">
            <h2>💉 البروتوكول الحالي</h2>
            <div class="actions">
                <a class="btn btn-sm" href="injections.php?tab=give">تسجيل جرعة</a>
                <a class="btn btn-light btn-sm" href="patient.php?id=<?= $id ?>&tab=inj&plan=<?= (int)$current['id'] ?>">تعديل</a>
                <a class="btn btn-light btn-sm" href="injection_statement.php?id=<?= $id ?>">🖨️ كشف حساب</a>
            </div>
        </div>
        <div class="grid4">
            <p><span class="muted">الدواء:</span> <strong><?= e($current['drug_name']) ?></strong></p>
            <p><span class="muted">الجرعة الأسبوعية:</span> <strong><?= e(num_fmt($current['weekly_units'])) ?> وحدة</strong></p>
            <p><span class="muted">سعر الوحدة:</span> <strong><?= e(money($current['unit_price'])) ?></strong></p>
            <p><span class="muted">تكلفة الأسبوع:</span>
               <strong><?= e(money((float)$current['weekly_units'] * (float)$current['unit_price'])) ?></strong></p>
            <p><span class="muted">بدأ في:</span> <?= e(fmt_date($current['start_date'])) ?></p>
            <p><span class="muted">الجرعة القادمة:</span>
                <?php if ($overdue): ?><span class="badge bad">متأخرة — <?= e(fmt_date($nextDate)) ?></span>
                <?php else: ?><strong><?= e(day_ar($nextDate)) ?> <?= e(fmt_date($nextDate)) ?></strong><?php endif; ?>
            </p>
        </div>
        <?php if ($current['notes']): ?><p><span class="muted">ملاحظات:</span> <?= e($current['notes']) ?></p><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (has_role('admin', 'doctor') && $showPlanForm): $pf = $editPlan ?: []; ?>
    <div class="card">
        <h2><?= $editPlan ? 'تعديل البروتوكول' : '➕ بروتوكول حقن جديد' ?></h2>
        <?php if (!$drugList): ?>
            <p class="muted">لا توجد أدوية مفعّلة — <a href="drugs.php?new=1">أضف دواءً أولًا</a>.</p>
        <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_plan">
            <input type="hidden" name="id" value="<?= $id ?>">
            <input type="hidden" name="plan_id" value="<?= (int)($pf['id'] ?? 0) ?>">
            <div class="grid4">
                <label>الدواء *
                    <select name="drug_id" id="plan-drug" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($drugList as $dr): ?>
                            <option value="<?= (int)$dr['id'] ?>" data-price="<?= e($dr['unit_price']) ?>"
                                <?= (int)($pf['drug_id'] ?? 0) === (int)$dr['id'] ? 'selected' : '' ?>>
                                <?= e($dr['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>الجرعة الأسبوعية (وحدة) *
                    <input type="number" step="0.5" min="0.5" name="weekly_units" id="plan-units"
                           value="<?= e($pf['weekly_units'] ?? '') ?>" required></label>
                <label>سعر الوحدة (<?= e(setting('currency', 'ج.م')) ?>) *
                    <input type="number" step="0.01" min="0" name="unit_price" id="plan-price"
                           value="<?= e($pf['unit_price'] ?? '') ?>" required></label>
                <label>تكلفة الأسبوع
                    <input id="plan-total" readonly value="0.00" style="background:#f0fdfa;font-weight:700"></label>
            </div>
            <div class="grid4">
                <label>تاريخ البداية <input type="date" name="start_date"
                    value="<?= e($pf['start_date'] ?? date('Y-m-d')) ?>" required></label>
                <label>تاريخ النهاية <input type="date" name="end_date" value="<?= e($pf['end_date'] ?? '') ?>"></label>
                <label>الحالة
                    <select name="status">
                        <?php foreach (INJ_STATUS as $k => $v): ?>
                            <option value="<?= e($k) ?>" <?= ($pf['status'] ?? 'active') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>ملاحظات <input name="plan_notes" value="<?= e($pf['notes'] ?? '') ?>"></label>
            </div>
            <div class="actions">
                <button class="btn" type="submit">حفظ البروتوكول</button>
                <?php if ($editPlan): ?><a class="btn btn-light" href="patient.php?id=<?= $id ?>&tab=inj">إلغاء</a><?php endif; ?>
            </div>
        </form>
        <script>
        (function () {
            var d = document.getElementById('plan-drug'), u = document.getElementById('plan-units'),
                p = document.getElementById('plan-price'), t = document.getElementById('plan-total');
            function calc() { t.value = ((parseFloat(u.value) || 0) * (parseFloat(p.value) || 0)).toFixed(2); }
            d.addEventListener('change', function () {
                var dp = d.options[d.selectedIndex].dataset.price;
                if (dp && parseFloat(dp) > 0 && !p.value) p.value = dp;
                calc();
            });
            u.addEventListener('input', calc);
            p.addEventListener('input', calc);
            calc();
        })();
        </script>
        <?php endif; ?>
    </div>
    <?php elseif (has_role('admin', 'doctor')): ?>
    <div class="card"><a class="btn" href="patient.php?id=<?= $id ?>&tab=inj&newplan=1">➕ بروتوكول حقن جديد</a></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-head">
            <h2>🧾 كشف حساب الوحدات</h2>
            <div class="actions">
                <a class="btn btn-light btn-sm" href="injection_statement.php?id=<?= $id ?>">🖨️ طباعة</a>
                <a class="btn btn-xls btn-sm" href="export.php?type=patient_injections&patient=<?= $id ?>">⬇ Excel</a>
            </div>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>التاريخ</th><th>الدواء</th><th>الوحدات</th><th>سعر الوحدة</th><th>المستحق</th>
                <th>المدفوع</th><th>الرصيد التراكمي</th><th>أعطاها</th><th></th></tr></thead>
            <tbody>
            <?php $running = 0.0; foreach ($doses as $r):
                $running += (float)$r['amount'] - (float)$r['paid'];
                $rest = (float)$r['amount'] - (float)$r['paid'];
            ?>
                <tr>
                    <td class="num"><?= e(fmt_date($r['dose_date'])) ?></td>
                    <td><?= e($r['drug_name']) ?><?php if ($r['notes']): ?><br><small class="muted"><?= e($r['notes']) ?></small><?php endif; ?></td>
                    <td class="num"><strong><?= e(num_fmt($r['units'])) ?></strong></td>
                    <td class="num"><?= e(money($r['unit_price'])) ?></td>
                    <td class="num"><?= e(money($r['amount'])) ?></td>
                    <td class="num"><?= e(money($r['paid'])) ?></td>
                    <td class="num"><strong style="color:<?= $running > 0.005 ? '#b91c1c' : '#15803d' ?>"><?= e(money($running)) ?></strong></td>
                    <td><?= e($r['uname'] ?? '—') ?></td>
                    <td>
                    <?php if ($rest > 0.005 && has_role('admin', 'reception')): ?>
                        <form method="post" class="inline-form" style="gap:4px">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="pay_inj">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="dose_id" value="<?= (int)$r['id'] ?>">
                            <input type="number" step="0.01" min="0.01" max="<?= e(number_format($rest, 2, '.', '')) ?>"
                                   name="pay" value="<?= e(number_format($rest, 2, '.', '')) ?>" style="width:90px;min-width:0">
                            <button class="btn btn-sm" type="submit">سداد</button>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$doses): ?><tr><td colspan="9" class="muted">لا توجد جرعات مسجّلة لهذا المريض.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($doses): ?>
            <tfoot><tr>
                <td colspan="2">الإجمالي (<?= count($doses) ?> جرعة)</td>
                <td class="num"><?= e(num_fmt($totUnits)) ?></td>
                <td></td>
                <td class="num"><?= e(money($totAmount)) ?></td>
                <td class="num"><?= e(money($totPaid)) ?></td>
                <td class="num"><?= e(money($injBalance)) ?></td>
                <td colspan="2"></td>
            </tr></tfoot>
            <?php endif; ?>
        </table></div>
    </div>

    <?php if (count($plansInj) > 1 || ($plansInj && !$current)): ?>
    <div class="card">
        <h2>📜 سجل البروتوكولات</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>الدواء</th><th>الجرعة</th><th>سعر الوحدة</th><th>من</th><th>إلى</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($plansInj as $pl): ?>
                <tr>
                    <td><?= e($pl['drug_name']) ?></td>
                    <td class="num"><?= e(num_fmt($pl['weekly_units'])) ?> وحدة</td>
                    <td class="num"><?= e(money($pl['unit_price'])) ?></td>
                    <td class="num"><?= e(fmt_date($pl['start_date'])) ?></td>
                    <td class="num"><?= e(fmt_date($pl['end_date'])) ?></td>
                    <td><span class="badge <?= e(INJ_BADGE[$pl['status']]) ?>"><?= e(INJ_STATUS[$pl['status']]) ?></span></td>
                    <td><?php if (has_role('admin', 'doctor')): ?>
                        <a class="btn btn-light btn-sm" href="patient.php?id=<?= $id ?>&tab=inj&plan=<?= (int)$pl['id'] ?>">تعديل</a>
                    <?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
    <?php endif; ?>

<?php elseif ($tab === 'appts'):
    $st = $pdo->prepare('SELECT * FROM appointments WHERE patient_id = ? ORDER BY adate DESC, atime DESC');
    $st->execute([$id]);
    $appts = $st->fetchAll();
?>
    <div class="card">
        <div class="card-head">
            <h2>المواعيد</h2>
            <a class="btn" href="appointments.php?patient=<?= $id ?>">+ حجز موعد</a>
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>التاريخ</th><th>اليوم</th><th>الوقت</th><th>النوع</th><th>الحالة</th><th>ملاحظات</th></tr></thead>
            <tbody>
            <?php foreach ($appts as $a): ?>
                <tr>
                    <td class="num"><?= e(fmt_date($a['adate'])) ?></td>
                    <td><?= e(day_ar($a['adate'])) ?></td>
                    <td class="num"><?= e(fmt_time($a['atime'])) ?></td>
                    <td><?= e(APPT_TYPES[$a['type']] ?? $a['type']) ?></td>
                    <td><span class="badge <?= e(APPT_BADGE[$a['status']]) ?>"><?= e(APPT_STATUS[$a['status']]) ?></span></td>
                    <td><?= e($a['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$appts): ?><tr><td colspan="6" class="muted">لا توجد مواعيد.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<?php elseif ($tab === 'pay' && has_role('admin', 'reception')):
    $st = $pdo->prepare('SELECT * FROM payments WHERE patient_id = ? ORDER BY pdate DESC, id DESC');
    $st->execute([$id]);
    $pays = $st->fetchAll();
    $sum = array_sum(array_map(fn($r) => (float)$r['amount'], $pays));
?>
    <div class="card">
        <h2>تسجيل دفعة</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_payment">
            <input type="hidden" name="id" value="<?= $id ?>">
            <div class="grid4">
                <label>التاريخ <input type="date" name="pdate" value="<?= date('Y-m-d') ?>" required></label>
                <label>المبلغ * <input type="number" step="0.01" min="0.01" name="amount" required></label>
                <label>طريقة الدفع
                    <select name="method">
                        <?php foreach (PAY_METHODS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                    </select>
                </label>
                <label>الخدمة
                    <select name="service">
                        <option value="كشف جديد">كشف جديد (<?= e(setting('price_new', '300')) ?>)</option>
                        <option value="متابعة">متابعة (<?= e(setting('price_followup', '150')) ?>)</option>
                        <option value="باقة">باقة / اشتراك</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </label>
            </div>
            <label>ملاحظات <input name="p_notes"></label>
            <button class="btn" type="submit">حفظ الدفعة</button>
        </form>
    </div>
    <div class="card">
        <h2>سجل المدفوعات — الإجمالي: <?= e(money($sum)) ?></h2>
        <div class="table-wrap"><table>
            <thead><tr><th>التاريخ</th><th>المبلغ</th><th>الطريقة</th><th>الخدمة</th><th>ملاحظات</th></tr></thead>
            <tbody>
            <?php foreach ($pays as $pay): ?>
                <tr>
                    <td class="num"><?= e(fmt_date($pay['pdate'])) ?></td>
                    <td class="num"><strong><?= e(money($pay['amount'])) ?></strong></td>
                    <td><?= e(PAY_METHODS[$pay['method']] ?? $pay['method']) ?></td>
                    <td><?= e($pay['service']) ?></td>
                    <td><?= e($pay['notes']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$pays): ?><tr><td colspan="5" class="muted">لا توجد مدفوعات.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
<?php endif;
page_footer();
