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

page_header('التقارير', 'reports.php');
$monthName = ['01'=>'يناير','02'=>'فبراير','03'=>'مارس','04'=>'أبريل','05'=>'مايو','06'=>'يونيو','07'=>'يوليو','08'=>'أغسطس','09'=>'سبتمبر','10'=>'أكتوبر','11'=>'نوفمبر','12'=>'ديسمبر'][substr($month, 5, 2)] ?? '';
?>
<div class="card">
    <form class="inline-form" method="get">
        <label>الشهر <input type="month" name="m" value="<?= e($month) ?>"></label>
        <button class="btn" type="submit">عرض التقرير</button>
        <button class="btn btn-light no-print" type="button" onclick="window.print()">🖨️ طباعة التقرير</button>
    </form>
</div>

<h2 style="margin-bottom:14px">تقرير شهر <?= e($monthName) ?> <?= e(substr($month, 0, 4)) ?></h2>

<div class="stats">
    <div class="stat accent"><div class="label">إجمالي الإيرادات</div><div class="value"><?= e(money($income)) ?></div></div>
    <div class="stat"><div class="label">إجمالي المصروفات</div><div class="value"><?= e(money($expense)) ?></div></div>
    <div class="stat"><div class="label">صافي الربح</div>
        <div class="value" style="color:<?= $income - $expense >= 0 ? '#15803d' : '#b91c1c' ?>"><?= e(money($income - $expense)) ?></div></div>
    <div class="stat"><div class="label">مرضى جدد</div><div class="value"><?= $newPatients ?></div></div>
</div>

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
