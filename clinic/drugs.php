<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('drug.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_drug') {
        deny_unless('drug.manage', 'drugs.php');
        $did = (int)($_POST['did'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $unitsPerPen = (float)($_POST['units_per_pen'] ?? 0);
        if ($name === '' || $unitsPerPen <= 0) {
            flash('اسم الدواء وعدد الوحدات في القلم مطلوبان.', 'danger');
            redirect('drugs.php' . ($did ? '?edit=' . $did : '?new=1'));
        }
        $data = [
            $name,
            $unitsPerPen,
            (float)($_POST['unit_price'] ?? 0),
            (float)($_POST['cost_per_pen'] ?? 0),
            (float)($_POST['low_units'] ?? 100),
            isset($_POST['active']) ? 1 : 0,
            trim($_POST['notes'] ?? ''),
        ];
        if ($did) {
            $pdo->prepare('UPDATE drugs SET name=?, units_per_pen=?, unit_price=?, cost_per_pen=?, low_units=?, active=?, notes=? WHERE id=?')
                ->execute([...$data, $did]);
            flash('تم تحديث بيانات الدواء.');
        } else {
            $pdo->prepare('INSERT INTO drugs (name, units_per_pen, unit_price, cost_per_pen, low_units, active, notes) VALUES (?,?,?,?,?,?,?)')
                ->execute($data);
            flash('تمت إضافة الدواء.');
        }
        redirect('drugs.php');
    }

    if ($action === 'del_drug') {
        deny_unless('drug.manage', 'drugs.php');
        $did = (int)($_POST['did'] ?? 0);
        $st = $pdo->prepare('SELECT COUNT(*) FROM injection_doses WHERE drug_id = ?');
        $st->execute([$did]);
        if ((int)$st->fetchColumn() > 0) {
            flash('لا يمكن حذف دواء له جرعات مسجّلة — أوقف تفعيله بدلًا من ذلك.', 'danger');
        } else {
            $pdo->prepare('DELETE FROM drugs WHERE id = ?')->execute([$did]);
            flash('تم حذف الدواء.');
        }
        redirect('drugs.php');
    }

    // استلام كمية جديدة للمخزن
    if ($action === 'add_batch') {
        deny_unless('drug.stock', 'drugs.php?stock=1');
        $drugId = (int)($_POST['drug_id'] ?? 0);
        $pens = (float)($_POST['pens'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM drugs WHERE id = ?');
        $st->execute([$drugId]);
        $drug = $st->fetch();
        if (!$drug || $pens <= 0) {
            flash('اختر الدواء وأدخل عدد الأقلام.', 'danger');
            redirect('drugs.php?stock=1');
        }
        $unitsTotal = $pens * (float)$drug['units_per_pen'];
        $costPerPen = ($_POST['cost_per_pen'] ?? '') !== ''
            ? (float)$_POST['cost_per_pen'] : (float)$drug['cost_per_pen'];

        $pdo->prepare(
            'INSERT INTO drug_batches (drug_id, batch_no, expiry_date, pens, units_total, cost_total, received_date, notes, created_by)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $drugId,
            trim($_POST['batch_no'] ?? ''),
            ($_POST['expiry_date'] ?? '') ?: null,
            $pens,
            $unitsTotal,
            $costPerPen * $pens,
            ($_POST['received_date'] ?? '') ?: date('Y-m-d'),
            trim($_POST['notes'] ?? ''),
            user()['id'],
        ]);

        // تسجيل تكلفة الشراء كمصروف حتى تظهر في صافي الربح
        if ($costPerPen > 0 && isset($_POST['as_expense'])) {
            $pdo->prepare('INSERT INTO expenses (edate, category, amount, notes, created_by) VALUES (?,?,?,?,?)')
                ->execute([
                    ($_POST['received_date'] ?? '') ?: date('Y-m-d'),
                    'supplies',
                    $costPerPen * $pens,
                    'شراء ' . num_fmt($pens) . ' قلم — ' . $drug['name'],
                    user()['id'],
                ]);
        }
        activity($pdo, 'stock', 'drug', $drugId, 'استلام ' . num_fmt($pens) . ' قلم (' . units_fmt($unitsTotal) . ') — ' . $drug['name']);
        flash('تم استلام ' . num_fmt($pens) . ' قلم (' . units_fmt($unitsTotal) . ') في المخزن.');
        redirect('drugs.php?stock=1');
    }

    if ($action === 'del_batch') {
        deny_unless('drug.stock', 'drugs.php?stock=1');
        $bid = (int)($_POST['bid'] ?? 0);
        $st = $pdo->prepare('SELECT units_used FROM drug_batches WHERE id = ?');
        $st->execute([$bid]);
        $used = (float)$st->fetchColumn();
        if ($used > 0) {
            flash('لا يمكن حذف دفعة صُرف منها ' . units_fmt($used) . '.', 'danger');
        } else {
            $pdo->prepare('DELETE FROM drug_batches WHERE id = ?')->execute([$bid]);
            flash('تم حذف الدفعة.');
        }
        redirect('drugs.php?stock=1');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$showDrugForm = isset($_GET['new']) || $editId;
$showStock = isset($_GET['stock']);

$d = ['id' => 0, 'name' => '', 'units_per_pen' => '300', 'unit_price' => '', 'cost_per_pen' => '',
      'low_units' => '100', 'active' => 1, 'notes' => ''];
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM drugs WHERE id = ?');
    $st->execute([$editId]);
    $d = $st->fetch();
    if (!$d) {
        flash('الدواء غير موجود.', 'danger');
        redirect('drugs.php');
    }
}

$drugs = $pdo->query(
    'SELECT d.*,
        COALESCE((SELECT SUM(units_total - units_used) FROM drug_batches b WHERE b.drug_id = d.id), 0) AS units_left,
        COALESCE((SELECT SUM(units) FROM injection_doses i WHERE i.drug_id = d.id), 0) AS units_given
     FROM drugs d ORDER BY d.active DESC, d.name'
)->fetchAll();

page_header('الأدوية والمخزون', 'drugs.php');
?>
<div class="tabs">
    <a href="drugs.php" class="<?= !$showStock ? 'active' : '' ?>">💊 الأدوية</a>
    <a href="drugs.php?stock=1" class="<?= $showStock ? 'active' : '' ?>">📦 المخزون والدفعات</a>
</div>

<?php if (!$showStock): ?>

    <?php if ($showDrugForm): ?>
    <div class="card">
        <h2><?= $editId ? 'تعديل: ' . e($d['name']) : 'إضافة دواء / حقنة' ?></h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_drug">
            <input type="hidden" name="did" value="<?= (int)$d['id'] ?>">
            <label>اسم الدواء * <input name="name" value="<?= e($d['name']) ?>" required
                placeholder="مثال: أوزمبك Ozempic (قلم 3 مل)"></label>
            <div class="grid4">
                <label>عدد الوحدات في القلم *
                    <input type="number" step="0.1" min="1" name="units_per_pen" value="<?= e($d['units_per_pen']) ?>" required>
                </label>
                <label>سعر بيع الوحدة (<?= e(setting('currency', 'ج.م')) ?>)
                    <input type="number" step="0.01" min="0" name="unit_price" value="<?= e($d['unit_price']) ?>">
                </label>
                <label>تكلفة شراء القلم
                    <input type="number" step="0.01" min="0" name="cost_per_pen" value="<?= e($d['cost_per_pen']) ?>">
                </label>
                <label>تنبيه عند نقص المخزون عن
                    <input type="number" step="0.1" min="0" name="low_units" value="<?= e($d['low_units']) ?>">
                </label>
            </div>
            <label>ملاحظات <input name="notes" value="<?= e($d['notes']) ?>"></label>
            <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="active" style="width:auto" <?= $d['active'] ? 'checked' : '' ?>>
                مفعّل (يظهر عند تسجيل الجرعات)
            </label>
            <div class="actions">
                <button class="btn" type="submit">حفظ</button>
                <a class="btn btn-light" href="drugs.php">إلغاء</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-head">
            <h2>💊 الأدوية</h2>
            <a class="btn" href="drugs.php?new=1">+ دواء جديد</a>
        </div>
        <p class="muted">سعر الوحدة هنا هو السعر الافتراضي — يمكن للطبيب تحديد سعر مختلف لكل مريض في بروتوكوله.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>الدواء</th><th>وحدات القلم</th><th>سعر الوحدة</th><th>تكلفة القلم</th>
                <th>ربح الوحدة</th><th>المتبقي بالمخزن</th><th>صُرف</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($drugs as $r):
                $unitCost = (float)$r['units_per_pen'] > 0 ? (float)$r['cost_per_pen'] / (float)$r['units_per_pen'] : 0;
                $profit = (float)$r['unit_price'] - $unitCost;
                $low = (float)$r['units_left'] <= (float)$r['low_units'];
            ?>
                <tr>
                    <td><strong><?= e($r['name']) ?></strong>
                        <?php if ($r['notes']): ?><br><small class="muted"><?= e($r['notes']) ?></small><?php endif; ?></td>
                    <td class="num"><?= e(num_fmt($r['units_per_pen'])) ?></td>
                    <td class="num"><?= e(money($r['unit_price'])) ?></td>
                    <td class="num"><?= e(money($r['cost_per_pen'])) ?></td>
                    <td class="num"><?= $r['cost_per_pen'] > 0
                        ? '<span style="color:' . ($profit >= 0 ? '#15803d' : '#b91c1c') . '">' . e(money($profit)) . '</span>'
                        : '<span class="muted">—</span>' ?></td>
                    <td class="num"><span class="badge <?= $low ? 'bad' : 'ok' ?>"><?= e(num_fmt($r['units_left'])) ?></span></td>
                    <td class="num"><?= e(num_fmt($r['units_given'])) ?></td>
                    <td><span class="badge <?= $r['active'] ? 'ok' : 'muted' ?>"><?= $r['active'] ? 'مفعّل' : 'موقوف' ?></span></td>
                    <td><div class="actions">
                        <a class="btn btn-light btn-sm" href="drugs.php?edit=<?= (int)$r['id'] ?>">تعديل</a>
                        <?php if (can('drug.manage')): ?>
                        <form method="post" data-confirm="حذف هذا الدواء؟">
                            <?= csrf_field() ?><input type="hidden" name="action" value="del_drug">
                            <input type="hidden" name="did" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                        </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$drugs): ?><tr><td colspan="9" class="muted">لا توجد أدوية — أضف أول دواء.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

<?php else:
    $batches = $pdo->query(
        'SELECT b.*, d.name AS drug_name, u.name AS uname
         FROM drug_batches b JOIN drugs d ON d.id = b.drug_id
         LEFT JOIN users u ON u.id = b.created_by
         ORDER BY (b.units_total <= b.units_used), (b.expiry_date IS NULL), b.expiry_date, b.id DESC'
    )->fetchAll();
    $soon = date('Y-m-d', strtotime('+60 days'));
    $today = date('Y-m-d');
?>
    <div class="card">
        <h2>📥 استلام كمية في المخزن</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add_batch">
            <div class="grid4">
                <label>الدواء *
                    <select name="drug_id" required>
                        <option value="">— اختر —</option>
                        <?php foreach ($drugs as $r): if (!$r['active']) continue; ?>
                            <option value="<?= (int)$r['id'] ?>" data-cost="<?= e($r['cost_per_pen']) ?>">
                                <?= e($r['name']) ?> (<?= e(num_fmt($r['units_per_pen'])) ?> وحدة/قلم)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>عدد الأقلام * <input type="number" step="0.1" min="0.1" name="pens" value="1" required></label>
                <label>تكلفة القلم <input type="number" step="0.01" min="0" name="cost_per_pen" placeholder="افتراضي من بيانات الدواء"></label>
                <label>تاريخ الاستلام <input type="date" name="received_date" value="<?= date('Y-m-d') ?>" required></label>
                <label>رقم التشغيلة <input name="batch_no" placeholder="اختياري"></label>
                <label>تاريخ الانتهاء <input type="date" name="expiry_date"></label>
                <label>ملاحظات <input name="notes"></label>
            </div>
            <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="as_expense" style="width:auto" checked>
                تسجيل التكلفة كمصروف (بند مستلزمات) ليظهر في صافي الربح
            </label>
            <button class="btn" type="submit">استلام في المخزن</button>
            <?php if (!$drugs): ?><p class="muted">أضف دواءً أولًا من تبويب الأدوية.</p><?php endif; ?>
        </form>
    </div>

    <?php $stockVal = stock_value($pdo); ?>
    <div class="stats">
        <div class="stat accent"><div class="label">قيمة المخزون بسعر التكلفة</div>
            <div class="value"><?= e(money($stockVal)) ?></div></div>
        <div class="stat"><div class="label">إجمالي الوحدات المتاحة</div>
            <div class="value"><?= e(num_fmt(array_sum(array_map(fn($r) => (float)$r['units_left'], $drugs)))) ?></div></div>
    </div>

    <div class="card">
        <h2>📦 الدفعات في المخزن</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>الدواء</th><th>التشغيلة</th><th>الانتهاء</th><th>الأقلام</th>
                <th>إجمالي الوحدات</th><th>صُرف</th><th>المتبقي</th><th>تكلفة الوحدة</th><th>الاستلام</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($batches as $b):
                $left = (float)$b['units_total'] - (float)$b['units_used'];
                $expired = $b['expiry_date'] && $b['expiry_date'] < $today;
                $expiring = $b['expiry_date'] && !$expired && $b['expiry_date'] <= $soon;
            ?>
                <tr<?= $left <= 0 ? ' style="opacity:.55"' : '' ?>>
                    <td><strong><?= e($b['drug_name']) ?></strong></td>
                    <td class="num"><?= e($b['batch_no'] ?: '—') ?></td>
                    <td class="num">
                        <?php if ($expired): ?><span class="badge bad">منتهية <?= e(fmt_date($b['expiry_date'])) ?></span>
                        <?php elseif ($expiring): ?><span class="badge warn">قرب الانتهاء <?= e(fmt_date($b['expiry_date'])) ?></span>
                        <?php else: ?><?= e(fmt_date($b['expiry_date'])) ?><?php endif; ?>
                    </td>
                    <td class="num"><?= e(num_fmt($b['pens'])) ?></td>
                    <td class="num"><?= e(num_fmt($b['units_total'])) ?></td>
                    <td class="num"><?= e(num_fmt($b['units_used'])) ?></td>
                    <td class="num"><span class="badge <?= $left > 0 ? 'ok' : 'muted' ?>"><?= e(num_fmt($left)) ?></span></td>
                    <td class="num"><?= e(money(batch_unit_cost($b))) ?></td>
                    <td class="num"><?= e(fmt_date($b['received_date'])) ?></td>
                    <td>
                    <?php if (can('drug.stock') && (float)$b['units_used'] <= 0): ?>
                        <form method="post" data-confirm="حذف هذه الدفعة؟">
                            <?= csrf_field() ?><input type="hidden" name="action" value="del_batch">
                            <input type="hidden" name="bid" value="<?= (int)$b['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit">حذف</button>
                        </form>
                    <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$batches): ?><tr><td colspan="10" class="muted">لا توجد دفعات في المخزن.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

    <script>
    // ملء تكلفة القلم تلقائيًا من بيانات الدواء المختار
    document.querySelector('select[name=drug_id]').addEventListener('change', function () {
        var cost = this.options[this.selectedIndex].dataset.cost;
        var field = document.querySelector('input[name=cost_per_pen]');
        if (cost && parseFloat(cost) > 0 && !field.value) field.value = cost;
    });
    </script>
<?php endif;
page_footer();
