<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

refresh_package_status($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    /* --------------------------------------------- كتالوج الباقات */
    if ($action === 'save_pkg' && has_role('admin')) {
        $pid = (int)($_POST['pid'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $sessions = max(1, (int)($_POST['sessions'] ?? 1));
        if ($name === '') {
            flash('اسم الباقة مطلوب.', 'danger');
            redirect('packages.php');
        }
        $data = [
            $name, $sessions,
            (float)($_POST['price'] ?? 0),
            max(0, (int)($_POST['validity_days'] ?? 90)),
            trim($_POST['includes'] ?? ''),
            isset($_POST['active']) ? 1 : 0,
        ];
        if ($pid) {
            $pdo->prepare('UPDATE packages SET name=?, sessions=?, price=?, validity_days=?, includes=?, active=? WHERE id=?')
                ->execute([...$data, $pid]);
            flash('تم تحديث الباقة.');
        } else {
            $pdo->prepare('INSERT INTO packages (name, sessions, price, validity_days, includes, active) VALUES (?,?,?,?,?,?)')
                ->execute($data);
            flash('تمت إضافة الباقة.');
        }
        redirect('packages.php');
    }

    if ($action === 'del_pkg' && has_role('admin')) {
        $pdo->prepare('DELETE FROM packages WHERE id = ?')->execute([(int)$_POST['pid']]);
        flash('تم حذف الباقة من الكتالوج (الباقات المُباعة للمرضى لم تتأثر).');
        redirect('packages.php');
    }

    /* --------------------------------------------- بيع باقة لمريض */
    if ($action === 'sell') {
        $patientId = posted_patient_id($pdo);
        $pkgId = (int)($_POST['package_id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM packages WHERE id = ?');
        $st->execute([$pkgId]);
        $pkg = $st->fetch();
        if (!$patientId || !$pkg) {
            flash('اختر المريض والباقة.', 'danger');
            redirect('packages.php?tab=sell');
        }
        $start = ($_POST['start_date'] ?? '') ?: date('Y-m-d');
        $price = ($_POST['price'] ?? '') !== '' ? (float)$_POST['price'] : (float)$pkg['price'];
        $paid = min(max(0.0, (float)($_POST['paid'] ?? 0)), $price);
        $sessions = max(1, (int)($_POST['sessions'] ?? $pkg['sessions']));
        $expiry = (int)$pkg['validity_days'] > 0
            ? date('Y-m-d', strtotime($start . ' +' . (int)$pkg['validity_days'] . ' days'))
            : null;

        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO patient_packages (patient_id, package_id, name, sessions_total, price, paid,
                 start_date, expiry_date, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)'
            )->execute([
                $patientId, $pkgId, $pkg['name'], $sessions, $price, $paid,
                $start, $expiry, trim($_POST['notes'] ?? ''), user()['id'],
            ]);
            $ppId = (int)$pdo->lastInsertId();

            if ($paid > 0) {
                $pdo->prepare(
                    'INSERT INTO payments (patient_id, pdate, amount, method, service, notes, created_by)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([
                    $patientId, $start, $paid,
                    array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash',
                    'باقة', $pkg['name'] . ' (' . $sessions . ' جلسة)', user()['id'],
                ]);
            }
            $pdo->commit();
        } catch (PDOException) {
            $pdo->rollBack();
            flash('تعذر تسجيل الباقة.', 'danger');
            redirect('packages.php?tab=sell');
        }
        flash('تم بيع الباقة (' . $sessions . ' جلسة)' . ($price - $paid > 0 ? ' — متبقٍ ' . money($price - $paid) : ''));
        redirect('patient.php?id=' . $patientId . '&tab=pkg');
    }

    /* --------------------------------------------- خصم / إرجاع جلسة */
    if ($action === 'use_session') {
        $ppId = (int)($_POST['pp_id'] ?? 0);
        $st = $pdo->prepare('SELECT pp.*, (SELECT COUNT(*) FROM package_uses u WHERE u.patient_package_id = pp.id) AS used
                             FROM patient_packages pp WHERE pp.id = ?');
        $st->execute([$ppId]);
        $pp = $st->fetch();
        if (!$pp) {
            redirect('packages.php?tab=sold');
        }
        if ((int)$pp['used'] >= (int)$pp['sessions_total']) {
            flash('الباقة مستهلكة بالكامل.', 'danger');
        } else {
            $pdo->prepare('INSERT INTO package_uses (patient_package_id, use_date, notes, created_by) VALUES (?,?,?,?)')
                ->execute([$ppId, ($_POST['use_date'] ?? '') ?: date('Y-m-d'), trim($_POST['notes'] ?? ''), user()['id']]);
            refresh_package_status($pdo);
            flash('تم خصم جلسة.');
        }
        redirect($_POST['back'] ?? 'packages.php?tab=sold');
    }

    if ($action === 'undo_session') {
        $useId = (int)($_POST['use_id'] ?? 0);
        $st = $pdo->prepare('SELECT patient_package_id FROM package_uses WHERE id = ?');
        $st->execute([$useId]);
        $ppId = (int)$st->fetchColumn();
        $pdo->prepare('DELETE FROM package_uses WHERE id = ?')->execute([$useId]);
        if ($ppId) {
            $pdo->prepare("UPDATE patient_packages SET status='active'
                           WHERE id=? AND status='finished'")->execute([$ppId]);
        }
        flash('تم التراجع عن خصم الجلسة.');
        redirect($_POST['back'] ?? 'packages.php?tab=sold');
    }

    if ($action === 'pay_pkg' && has_role('admin', 'reception')) {
        $ppId = (int)($_POST['pp_id'] ?? 0);
        $pay = (float)($_POST['pay'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM patient_packages WHERE id = ?');
        $st->execute([$ppId]);
        $pp = $st->fetch();
        if ($pp && $pay > 0) {
            $pay = min($pay, (float)$pp['price'] - (float)$pp['paid']);
            $pdo->beginTransaction();
            try {
                $pdo->prepare('UPDATE patient_packages SET paid = paid + ? WHERE id = ?')->execute([$pay, $ppId]);
                $pdo->prepare('INSERT INTO payments (patient_id, pdate, amount, method, service, notes, created_by)
                               VALUES (?,?,?,?,?,?,?)')
                    ->execute([
                        (int)$pp['patient_id'], date('Y-m-d'), $pay,
                        array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash',
                        'باقة', 'سداد باقة — ' . $pp['name'], user()['id'],
                    ]);
                $pdo->commit();
                flash('تم سداد ' . money($pay) . '.');
            } catch (PDOException) {
                $pdo->rollBack();
                flash('تعذر تسجيل السداد.', 'danger');
            }
        }
        redirect($_POST['back'] ?? 'packages.php?tab=sold');
    }

    if ($action === 'cancel_pkg' && has_role('admin')) {
        $pdo->prepare("UPDATE patient_packages SET status='cancelled' WHERE id=?")->execute([(int)$_POST['pp_id']]);
        flash('تم إلغاء الباقة.');
        redirect('packages.php?tab=sold');
    }
}

$tab = $_GET['tab'] ?? 'sold';
$catalog = $pdo->query('SELECT * FROM packages ORDER BY active DESC, name')->fetchAll();

page_header('باقات الجلسات', 'packages.php');

$tabs = ['sold' => 'باقات المرضى', 'sell' => 'بيع باقة'];
if (has_role('admin')) {
    $tabs['catalog'] = 'كتالوج الباقات';
}
?>
<div class="tabs">
    <?php foreach ($tabs as $k => $label): ?>
        <a href="packages.php?tab=<?= $k ?>" class="<?= $tab === $k ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'catalog' && has_role('admin')):
    $editId = (int)($_GET['edit'] ?? 0);
    $pk = ['id' => 0, 'name' => '', 'sessions' => 8, 'price' => '', 'validity_days' => 90, 'includes' => '', 'active' => 1];
    foreach ($catalog as $c) {
        if ((int)$c['id'] === $editId) { $pk = $c; break; }
    }
?>
<div class="card">
    <h2><?= $editId ? 'تعديل باقة: ' . e($pk['name']) : '➕ باقة جديدة' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_pkg">
        <input type="hidden" name="pid" value="<?= (int)$pk['id'] ?>">
        <div class="grid4">
            <label>اسم الباقة * <input name="name" value="<?= e($pk['name']) ?>" required
                placeholder="مثال: باقة 8 جلسات متابعة"></label>
            <label>عدد الجلسات * <input type="number" min="1" name="sessions" value="<?= e($pk['sessions']) ?>" required></label>
            <label>السعر (<?= e(setting('currency', 'ج.م')) ?>) <input type="number" step="0.01" min="0" name="price" value="<?= e($pk['price']) ?>"></label>
            <label>الصلاحية (أيام) <input type="number" min="0" name="validity_days" value="<?= e($pk['validity_days']) ?>"
                title="0 = بدون تاريخ انتهاء"></label>
        </div>
        <label>ما تشمله الباقة <textarea name="includes" rows="2"><?= e($pk['includes']) ?></textarea></label>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="active" style="width:auto" <?= $pk['active'] ? 'checked' : '' ?>> مفعّلة
        </label>
        <div class="actions">
            <button class="btn" type="submit">حفظ</button>
            <?php if ($editId): ?><a class="btn btn-light" href="packages.php?tab=catalog">إلغاء</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>📦 الكتالوج</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الباقة</th><th>الجلسات</th><th>السعر</th><th>سعر الجلسة</th><th>الصلاحية</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($catalog as $c): ?>
            <tr>
                <td><strong><?= e($c['name']) ?></strong>
                    <?php if ($c['includes']): ?><br><small class="muted"><?= e($c['includes']) ?></small><?php endif; ?></td>
                <td class="num"><?= (int)$c['sessions'] ?></td>
                <td class="num"><?= e(money($c['price'])) ?></td>
                <td class="num"><?= e(money((int)$c['sessions'] > 0 ? (float)$c['price'] / (int)$c['sessions'] : 0)) ?></td>
                <td class="num"><?= (int)$c['validity_days'] > 0 ? (int)$c['validity_days'] . ' يوم' : 'دائمة' ?></td>
                <td><span class="badge <?= $c['active'] ? 'ok' : 'muted' ?>"><?= $c['active'] ? 'مفعّلة' : 'موقوفة' ?></span></td>
                <td><div class="actions">
                    <a class="btn btn-light btn-sm" href="packages.php?tab=catalog&edit=<?= (int)$c['id'] ?>">تعديل</a>
                    <form method="post" data-confirm="حذف الباقة من الكتالوج؟">
                        <?= csrf_field() ?><input type="hidden" name="action" value="del_pkg">
                        <input type="hidden" name="pid" value="<?= (int)$c['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$catalog): ?><tr><td colspan="7" class="muted">لا توجد باقات — أضف أول باقة.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php elseif ($tab === 'sell'): ?>
<div class="card">
    <h2>🛒 بيع باقة لمريض</h2>
    <?php if (!array_filter($catalog, fn($c) => $c['active'])): ?>
        <p class="muted">لا توجد باقات مفعّلة — <?= has_role('admin')
            ? '<a href="packages.php?tab=catalog">أضف باقة أولًا</a>' : 'اطلب من المدير إضافة باقة' ?>.</p>
    <?php else: ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="sell">
        <div class="grid4">
            <label>المريض * <?= patient_picker($pdo) ?></label>
            <label>الباقة *
                <select name="package_id" id="s-pkg" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($catalog as $c): if (!$c['active']) continue; ?>
                        <option value="<?= (int)$c['id'] ?>" data-sessions="<?= (int)$c['sessions'] ?>"
                                data-price="<?= e($c['price']) ?>">
                            <?= e($c['name']) ?> — <?= (int)$c['sessions'] ?> جلسة — <?= e(money($c['price'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>عدد الجلسات <input type="number" min="1" name="sessions" id="s-sessions"></label>
            <label>تاريخ البداية <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required></label>
        </div>
        <div class="grid4">
            <label>السعر <input type="number" step="0.01" min="0" name="price" id="s-price"></label>
            <label>المدفوع الآن <input type="number" step="0.01" min="0" name="paid" id="s-paid"></label>
            <label>طريقة الدفع
                <select name="method">
                    <?php foreach (PAY_METHODS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>ملاحظات <input name="notes"></label>
        </div>
        <button class="btn" type="submit">تسجيل الباقة</button>
    </form>
    <script>
    document.getElementById('s-pkg').addEventListener('change', function () {
        var o = this.options[this.selectedIndex];
        document.getElementById('s-sessions').value = o.dataset.sessions || '';
        document.getElementById('s-price').value = o.dataset.price || '';
        document.getElementById('s-paid').value = o.dataset.price || '';
    });
    </script>
    <?php endif; ?>
</div>

<?php else:
    [$df, $dfArgs] = doctor_filter('p');
    $status = $_GET['status'] ?? 'active';
    $stFilter = array_key_exists($status, PKG_STATUS) ? $status : '';
    $where = $stFilter !== '' ? 'AND pp.status = ?' : '';
    $args = array_merge($dfArgs, $stFilter !== '' ? [$stFilter] : []);

    $st = $pdo->prepare(
        "SELECT pp.*, p.name AS pname, p.code, p.phone,
            (SELECT COUNT(*) FROM package_uses u WHERE u.patient_package_id = pp.id) AS used
         FROM patient_packages pp JOIN patients p ON p.id = pp.patient_id
         WHERE 1=1 $df $where
         ORDER BY pp.status='active' DESC, pp.expiry_date IS NULL, pp.expiry_date, pp.id DESC"
    );
    $st->execute($args);
    $sold = $st->fetchAll();
    $owed = array_sum(array_map(fn($r) => (float)$r['price'] - (float)$r['paid'], $sold));
?>
<div class="card">
    <div class="card-head">
        <h2>🎟️ باقات المرضى</h2>
        <form class="inline-form" method="get">
            <input type="hidden" name="tab" value="sold">
            <label>الحالة
                <select name="status" onchange="this.form.submit()">
                    <option value="">الكل</option>
                    <?php foreach (PKG_STATUS as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= $stFilter === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <a class="btn btn-sm" href="packages.php?tab=sell">+ بيع باقة</a>
        </form>
    </div>
    <?php if ($owed > 0.005): ?>
        <p><span class="badge bad">متأخرات الباقات: <?= e(money($owed)) ?></span></p>
    <?php endif; ?>
    <div class="table-wrap"><table>
        <thead><tr><th>المريض</th><th>الباقة</th><th>الجلسات</th><th>المتبقي</th><th>تنتهي</th>
            <th>السعر</th><th>المدفوع</th><th>الحالة</th><th>إجراءات</th></tr></thead>
        <tbody>
        <?php foreach ($sold as $r):
            $left = (int)$r['sessions_total'] - (int)$r['used'];
            $rest = (float)$r['price'] - (float)$r['paid'];
            $soon = $r['expiry_date'] && $r['status'] === 'active'
                    && $r['expiry_date'] <= date('Y-m-d', strtotime('+14 days'));
        ?>
            <tr>
                <td><a href="patient.php?id=<?= (int)$r['patient_id'] ?>&tab=pkg"><?= e($r['pname']) ?></a>
                    <small class="muted"><?= e($r['code']) ?></small></td>
                <td><?= e($r['name']) ?></td>
                <td class="num"><?= (int)$r['used'] ?> / <?= (int)$r['sessions_total'] ?></td>
                <td class="num"><span class="badge <?= $left > 0 ? 'ok' : 'muted' ?>"><?= $left ?></span></td>
                <td class="num"><?= $soon
                    ? '<span class="badge warn">' . e(fmt_date($r['expiry_date'])) . '</span>'
                    : e(fmt_date($r['expiry_date'])) ?></td>
                <td class="num"><?= e(money($r['price'])) ?></td>
                <td class="num"><?= $rest > 0.005
                    ? e(money($r['paid'])) . ' <span class="badge bad">متبقٍ ' . e(money($rest)) . '</span>'
                    : e(money($r['paid'])) ?></td>
                <td><span class="badge <?= e(PKG_BADGE[$r['status']]) ?>"><?= e(PKG_STATUS[$r['status']]) ?></span></td>
                <td><div class="actions">
                    <?php if ($r['status'] === 'active' && $left > 0): ?>
                    <form method="post">
                        <?= csrf_field() ?><input type="hidden" name="action" value="use_session">
                        <input type="hidden" name="pp_id" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="back" value="packages.php?tab=sold&status=<?= e($stFilter) ?>">
                        <button class="btn btn-sm" type="submit">خصم جلسة</button>
                    </form>
                    <?php endif; ?>
                    <a class="btn btn-light btn-sm" href="patient.php?id=<?= (int)$r['patient_id'] ?>&tab=pkg">التفاصيل</a>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$sold): ?><tr><td colspan="9" class="muted">لا توجد باقات.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php endif;
page_footer();
