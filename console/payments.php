<?php
/** كل المدفوعات عبر العيادات، مع تأكيد التحويلات المعلّقة. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $payId = (int)($_POST['pay_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    $st = $pdo->prepare('SELECT * FROM payments WHERE id = ?');
    $st->execute([$payId]);
    $pay = $st->fetch();
    if (!$pay) {
        flash('الدفعة غير موجودة.', 'danger');
        redirect('payments.php');
    }

    if (in_array($action, ['confirm', 'reject'], true)) {
        $new = $action === 'confirm' ? 'confirmed' : 'failed';
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE payments SET status = ? WHERE id = ?')->execute([$new, $payId]);
            if ($pay['invoice_id']) {
                invoice_recalc($pdo, (int)$pay['invoice_id']);
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('تعذر تحديث الدفعة: ' . $ex->getMessage(), 'danger');
            redirect('payments.php');
        }
        log_action($pdo, 'update', 'payment', $payId,
            ($new === 'confirmed' ? 'تأكيد' : 'رفض') . ' دفعة ' . money($pay['amount']));
        flash($new === 'confirmed' ? 'تم تأكيد الدفعة وتحديث الاشتراك.' : 'تم رفض الدفعة.');
        redirect('payments.php' . (isset($_POST['back']) ? '?status=pending' : ''));
    }
}

$status = $_GET['status'] ?? '';
$method = $_GET['method'] ?? '';
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');

$sql = 'SELECT pm.*, c.name AS clinic_name, i.number FROM payments pm
        JOIN clinics c ON c.id = pm.clinic_id
        LEFT JOIN invoices i ON i.id = pm.invoice_id
        WHERE pm.pdate BETWEEN ? AND ?';
$args = [$from, $to];
if (array_key_exists($status, PAY_STATUS)) {
    $sql .= ' AND pm.status = ?';
    $args[] = $status;
}
if (array_key_exists($method, PAY_METHODS)) {
    $sql .= ' AND pm.method = ?';
    $args[] = $method;
}
$sql .= ' ORDER BY pm.id DESC LIMIT 300';
$st = $pdo->prepare($sql);
$st->execute($args);
$pays = $st->fetchAll();

$confirmedTotal = 0.0;
$byMethod = [];
foreach ($pays as $p) {
    if ($p['status'] === 'confirmed') {
        $confirmedTotal += (float)$p['amount'];
        $byMethod[$p['method']] = ($byMethod[$p['method']] ?? 0) + (float)$p['amount'];
    }
}

page_header('المدفوعات', 'payments.php');
?>
<div class="card">
    <h2>💳 المدفوعات</h2>
    <form class="inline-form" method="get">
        <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
        <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
        <select name="status">
            <option value="">كل الحالات</option>
            <?php foreach (PAY_STATUS as $k => $l): ?>
                <option value="<?= e($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="method">
            <option value="">كل الوسائل</option>
            <?php foreach (PAY_METHODS as $k => $l): ?>
                <option value="<?= e($k) ?>" <?= $method === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-light" type="submit">عرض</button>
    </form>
</div>

<div class="stats">
    <div class="stat accent"><div class="label">إجمالي المؤكد في الفترة</div><div class="value"><?= e(money($confirmedTotal)) ?></div></div>
    <?php foreach (PAY_METHODS as $k => $l): ?>
        <div class="stat"><div class="label"><?= e($l) ?></div><div class="value"><?= e(money($byMethod[$k] ?? 0)) ?></div></div>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>العيادة</th><th>الفاتورة</th><th>المبلغ</th><th>الوسيلة</th><th>المرجع</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?>
            <tr>
                <td class="num"><?= e(fmt_date($p['pdate'])) ?></td>
                <td><a href="clinic.php?id=<?= (int)$p['clinic_id'] ?>"><?= e($p['clinic_name']) ?></a></td>
                <td dir="ltr"><?= $p['invoice_id']
                    ? '<a href="invoice.php?id=' . (int)$p['invoice_id'] . '">' . e($p['number']) . '</a>'
                    : '—' ?></td>
                <td class="num"><strong><?= e(money($p['amount'])) ?></strong></td>
                <td><?= e(PAY_METHODS[$p['method']] ?? $p['method']) ?></td>
                <td dir="ltr"><small><?= e($p['reference'] ?: ($p['gateway_txn_id'] ?: '—')) ?></small></td>
                <td><span class="badge <?= e(PAY_STATUS_BADGE[$p['status']]) ?>"><?= e(PAY_STATUS[$p['status']]) ?></span></td>
                <td><?php if ($p['status'] === 'pending'): ?>
                    <div class="actions">
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="confirm">
                            <input type="hidden" name="pay_id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm" type="submit">تأكيد</button></form>
                        <form method="post" onsubmit="return confirm('رفض هذه الدفعة؟')"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="pay_id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">رفض</button></form>
                    </div>
                <?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pays): ?><tr><td colspan="8" class="muted">لا توجد مدفوعات في هذه الفترة.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
