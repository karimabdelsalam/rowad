<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin', 'reception');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            flash('أدخل مبلغًا صحيحًا.', 'danger');
            redirect('payments.php');
        }
        $method = array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash';
        $st = $pdo->prepare('INSERT INTO payments (patient_id, pdate, amount, method, service, notes, created_by) VALUES (?,?,?,?,?,?,?)');
        $st->execute([
            posted_patient_id($pdo), // اختياري
            ($_POST['pdate'] ?? '') ?: date('Y-m-d'),
            $amount,
            $method,
            trim($_POST['service'] ?? ''),
            trim($_POST['notes'] ?? ''),
            user()['id'],
        ]);
        flash('تم تسجيل الدفعة.');
        redirect('payments.php');
    }

    if ($action === 'delete' && has_role('admin')) {
        $pdo->prepare('DELETE FROM payments WHERE id = ?')->execute([(int)$_POST['payid']]);
        flash('تم حذف الدفعة.');
        redirect('payments.php?from=' . urlencode($_POST['from'] ?? '') . '&to=' . urlencode($_POST['to'] ?? ''));
    }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$validDate = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
if (!$validDate($from)) $from = date('Y-m-01');
if (!$validDate($to)) $to = date('Y-m-d');

$st = $pdo->prepare(
    'SELECT pay.*, p.name AS pname, u.name AS uname FROM payments pay
     LEFT JOIN patients p ON p.id = pay.patient_id
     LEFT JOIN users u ON u.id = pay.created_by
     WHERE pay.pdate BETWEEN ? AND ? ORDER BY pay.pdate DESC, pay.id DESC'
);
$st->execute([$from, $to]);
$rows = $st->fetchAll();
$sum = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));

page_header('المدفوعات', 'payments.php');
?>
<div class="card">
    <h2>تسجيل دفعة</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="grid4">
            <label>المريض (اختياري) <?= patient_picker($pdo, 'patient_id', null, false) ?></label>
            <label>التاريخ <input type="date" name="pdate" value="<?= date('Y-m-d') ?>" required></label>
            <label>المبلغ * <input type="number" step="0.01" min="0.01" name="amount" required></label>
            <label>طريقة الدفع
                <select name="method">
                    <?php foreach (PAY_METHODS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </label>
        </div>
        <div class="grid2">
            <label>الخدمة
                <select name="service">
                    <option value="كشف جديد">كشف جديد (<?= e(setting('price_new', '300')) ?>)</option>
                    <option value="متابعة">متابعة (<?= e(setting('price_followup', '150')) ?>)</option>
                    <option value="باقة">باقة / اشتراك</option>
                    <option value="أخرى">أخرى</option>
                </select>
            </label>
            <label>ملاحظات <input name="notes"></label>
        </div>
        <button class="btn" type="submit">حفظ الدفعة</button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h2>💰 المدفوعات من <?= e(fmt_date($from)) ?> إلى <?= e(fmt_date($to)) ?></h2>
        <form class="inline-form" method="get">
            <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="btn btn-light btn-sm" type="submit">عرض</button>
            <a class="btn btn-xls btn-sm" href="export.php?type=payments&from=<?= e($from) ?>&to=<?= e($to) ?>">⬇ تصدير Excel</a>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>المريض</th><th>المبلغ</th><th>الطريقة</th><th>الخدمة</th><th>ملاحظات</th><th>سجّلها</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="num"><?= e(fmt_date($r['pdate'])) ?></td>
                <td><?= $r['patient_id'] ? '<a href="patient.php?id=' . (int)$r['patient_id'] . '">' . e($r['pname']) . '</a>' : '<span class="muted">—</span>' ?></td>
                <td class="num"><strong><?= e(money($r['amount'])) ?></strong></td>
                <td><?= e(PAY_METHODS[$r['method']] ?? $r['method']) ?></td>
                <td><?= e($r['service']) ?></td>
                <td><?= e($r['notes']) ?></td>
                <td><?= e($r['uname'] ?? '—') ?></td>
                <td>
                <?php if (has_role('admin')): ?>
                    <form method="post" data-confirm="حذف هذه الدفعة؟">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                        <input type="hidden" name="payid" value="<?= (int)$r['id'] ?>">
                        <input type="hidden" name="from" value="<?= e($from) ?>"><input type="hidden" name="to" value="<?= e($to) ?>">
                        <button class="btn btn-light btn-sm" type="submit">حذف</button>
                    </form>
                <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted">لا توجد مدفوعات في هذه الفترة.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td colspan="2">الإجمالي (<?= count($rows) ?> دفعة)</td><td class="num" colspan="6"><?= e(money($sum)) ?></td></tr></tfoot>
    </table></div>
</div>
<?php page_footer();
