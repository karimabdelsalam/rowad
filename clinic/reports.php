<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');

$month = $_GET['m'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month) || !strtotime($month . '-01')) {
    $month = date('Y-m');
}
$from = $month . '-01';
$to = date('Y-m-t', strtotime($from));

$q = fn(string $sql) => $pdo->prepare($sql);

$st = $q('SELECT COALESCE(SUM(amount),0) FROM payments WHERE pdate BETWEEN ? AND ?');
$st->execute([$from, $to]);
$income = (float)$st->fetchColumn();

$st = $q('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE edate BETWEEN ? AND ?');
$st->execute([$from, $to]);
$expense = (float)$st->fetchColumn();

$st = $q('SELECT COUNT(*) FROM patients WHERE created_at BETWEEN ? AND ?');
$st->execute([$from, $to . ' 23:59:59']);
$newPatients = (int)$st->fetchColumn();

$st = $q('SELECT status, COUNT(*) c FROM appointments WHERE adate BETWEEN ? AND ? GROUP BY status');
$st->execute([$from, $to]);
$apptStats = $st->fetchAll(PDO::FETCH_KEY_PAIR);
$apptTotal = array_sum($apptStats);

$st = $q('SELECT pdate, SUM(amount) total, COUNT(*) c FROM payments WHERE pdate BETWEEN ? AND ? GROUP BY pdate ORDER BY pdate');
$st->execute([$from, $to]);
$byDay = $st->fetchAll();

$st = $q("SELECT COALESCE(NULLIF(service,''), 'غير محدد') s, SUM(amount) total, COUNT(*) c
          FROM payments WHERE pdate BETWEEN ? AND ? GROUP BY s ORDER BY total DESC");
$st->execute([$from, $to]);
$byService = $st->fetchAll();

$st = $q('SELECT category, SUM(amount) total FROM expenses WHERE edate BETWEEN ? AND ? GROUP BY category ORDER BY total DESC');
$st->execute([$from, $to]);
$expByCat = $st->fetchAll();

$st = $q('SELECT d.name, COUNT(*) c, SUM(i.units) units, SUM(i.amount) amount, SUM(i.paid) paid,
                 SUM(i.units * (i.unit_price - i.unit_cost)) profit
          FROM injection_doses i JOIN drugs d ON d.id = i.drug_id
          WHERE i.dose_date BETWEEN ? AND ? GROUP BY d.id, d.name ORDER BY amount DESC');
$st->execute([$from, $to]);
$injByDrug = $st->fetchAll();
$injUnits = array_sum(array_map(fn($r) => (float)$r['units'], $injByDrug));
$injAmount = array_sum(array_map(fn($r) => (float)$r['amount'], $injByDrug));
$injPaid = array_sum(array_map(fn($r) => (float)$r['paid'], $injByDrug));
$injProfit = array_sum(array_map(fn($r) => (float)$r['profit'], $injByDrug));

page_header('التقارير', 'reports.php');
?>
<div class="card">
    <form class="inline-form" method="get">
        <label>الشهر <input type="month" name="m" value="<?= e($month) ?>"></label>
        <button class="btn" type="submit">عرض التقرير</button>
        <a class="btn btn-xls no-print" href="export.php?type=report&m=<?= e($month) ?>">⬇ تصدير Excel</a>
        <button class="btn btn-light no-print" type="button" onclick="window.print()">🖨️ طباعة التقرير</button>
    </form>
</div>

<h2 style="margin-bottom:14px">تقرير شهر <?= e(month_ar($month)) ?></h2>

<div class="stats">
    <div class="stat accent"><div class="label">إجمالي الإيرادات</div><div class="value"><?= e(money($income)) ?></div></div>
    <div class="stat"><div class="label">إجمالي المصروفات</div><div class="value"><?= e(money($expense)) ?></div></div>
    <div class="stat"><div class="label">صافي الربح</div>
        <div class="value" style="color:<?= $income - $expense >= 0 ? '#15803d' : '#b91c1c' ?>"><?= e(money($income - $expense)) ?></div></div>
    <div class="stat"><div class="label">مرضى جدد</div><div class="value"><?= $newPatients ?></div></div>
    <?php $stockVal = stock_value($pdo); if ($stockVal > 0): ?>
    <div class="stat"><div class="label">قيمة المخزون الحالي</div><div class="value"><?= e(money($stockVal)) ?></div></div>
    <?php endif; ?>
</div>
<?php if ($stockVal > 0): ?>
<p class="muted" style="margin:-8px 0 18px">
    ملاحظة: ثمن الأقلام يُسجَّل كمصروف وقت الشراء، بينما إيرادها يدخل تدريجيًا مع صرف الوحدات.
    لذلك قد يظهر صافي الربح منخفضًا في شهر شراء كمية كبيرة —
    قيمة المخزون أعلاه (<?= e(money($stockVal)) ?>) هي رأس مال ما زال في الأقلام ولم يُبَع بعد.
</p>
<?php endif; ?>

<div class="card">
    <h2>📅 المواعيد (<?= $apptTotal ?>)</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الحالة</th><th>العدد</th><th>النسبة</th></tr></thead>
        <tbody>
        <?php foreach (APPT_STATUS as $k => $label): $c = (int)($apptStats[$k] ?? 0); ?>
            <tr>
                <td><span class="badge <?= e(APPT_BADGE[$k]) ?>"><?= e($label) ?></span></td>
                <td class="num"><?= $c ?></td>
                <td class="num"><?= $apptTotal ? round($c * 100 / $apptTotal) : 0 ?>%</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<div class="grid2" style="gap:20px;align-items:start">
    <div class="card">
        <h2>💰 الإيرادات حسب الخدمة</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>الخدمة</th><th>عدد الدفعات</th><th>الإجمالي</th></tr></thead>
            <tbody>
            <?php foreach ($byService as $r): ?>
                <tr><td><?= e($r['s']) ?></td><td class="num"><?= (int)$r['c'] ?></td><td class="num"><strong><?= e(money($r['total'])) ?></strong></td></tr>
            <?php endforeach; ?>
            <?php if (!$byService): ?><tr><td colspan="3" class="muted">لا توجد بيانات.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
    <div class="card">
        <h2>🧾 المصروفات حسب البند</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>البند</th><th>الإجمالي</th></tr></thead>
            <tbody>
            <?php foreach ($expByCat as $r): ?>
                <tr><td><?= e(EXPENSE_CATS[$r['category']] ?? $r['category']) ?></td><td class="num"><strong><?= e(money($r['total'])) ?></strong></td></tr>
            <?php endforeach; ?>
            <?php if (!$expByCat): ?><tr><td colspan="2" class="muted">لا توجد بيانات.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>

<?php if ($injByDrug): ?>
<div class="card">
    <div class="card-head">
        <h2>💉 الحقن — المحاسبة بالوحدات</h2>
        <span class="badge <?= $injAmount - $injPaid > 0.005 ? 'bad' : 'ok' ?>">
            متأخرات: <?= e(money($injAmount - $injPaid)) ?></span>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>الدواء</th><th>عدد الجرعات</th><th>الوحدات</th><th>متوسط سعر الوحدة</th>
            <th>المستحق</th><th>المحصَّل</th><th>الربح</th></tr></thead>
        <tbody>
        <?php foreach ($injByDrug as $r):
            $avg = (float)$r['units'] > 0 ? (float)$r['amount'] / (float)$r['units'] : 0; ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong></td>
                <td class="num"><?= (int)$r['c'] ?></td>
                <td class="num"><strong><?= e(num_fmt($r['units'])) ?></strong></td>
                <td class="num"><?= e(money($avg)) ?></td>
                <td class="num"><?= e(money($r['amount'])) ?></td>
                <td class="num"><?= e(money($r['paid'])) ?></td>
                <td class="num" style="color:<?= (float)$r['profit'] >= 0 ? '#15803d' : '#b91c1c' ?>">
                    <?= e(money($r['profit'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr>
            <td>الإجمالي</td>
            <td class="num"><?= array_sum(array_map(fn($r) => (int)$r['c'], $injByDrug)) ?></td>
            <td class="num"><?= e(num_fmt($injUnits)) ?></td>
            <td></td>
            <td class="num"><?= e(money($injAmount)) ?></td>
            <td class="num"><?= e(money($injPaid)) ?></td>
            <td class="num"><?= e(money($injProfit)) ?></td>
        </tr></tfoot>
    </table></div>
    <p class="muted">الإيرادات أعلاه تشمل المحصَّل من الحقن ضمن إجمالي إيرادات الشهر.
       «الربح» = (سعر الوحدة − تكلفتها من المخزن) × عدد الوحدات.</p>
</div>
<?php endif; ?>

<div class="card">
    <h2>الإيرادات اليومية</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>اليوم</th><th>عدد الدفعات</th><th>الإجمالي</th></tr></thead>
        <tbody>
        <?php foreach ($byDay as $r): ?>
            <tr>
                <td class="num"><?= e(fmt_date($r['pdate'])) ?></td>
                <td><?= e(day_ar($r['pdate'])) ?></td>
                <td class="num"><?= (int)$r['c'] ?></td>
                <td class="num"><strong><?= e(money($r['total'])) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$byDay): ?><tr><td colspan="4" class="muted">لا توجد إيرادات في هذا الشهر.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="3">الإجمالي</td><td class="num"><?= e(money($income)) ?></td></tr></tfoot>
    </table></div>
</div>
<?php page_footer();
