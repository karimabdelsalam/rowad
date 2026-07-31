<?php
/** قائمة الفواتير وإصدار فاتورة جديدة. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'create') {
        $clinicId = (int)($_POST['clinic_id'] ?? 0);
        $st = $pdo->prepare('SELECT id FROM clinics WHERE id = ?');
        $st->execute([$clinicId]);
        if (!$st->fetchColumn()) {
            flash('اختر العيادة.', 'danger');
            redirect('invoices.php?new=1');
        }
        $amount = max(0, (float)($_POST['amount'] ?? 0));
        $months = max(0, (int)($_POST['months'] ?? 0));
        if ($amount <= 0) {
            flash('أدخل مبلغ الفاتورة.', 'danger');
            redirect('invoices.php?new=1&clinic=' . $clinicId);
        }
        $number = next_invoice_number($pdo);
        $pdo->prepare('INSERT INTO invoices (clinic_id, number, issue_date, due_date, amount, months,
                       plan_name, notes, pay_token, created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                $clinicId, $number,
                ($_POST['issue_date'] ?? '') ?: date('Y-m-d'),
                ($_POST['due_date'] ?? '') ?: null,
                $amount, $months,
                trim($_POST['plan_name'] ?? ''),
                trim($_POST['notes'] ?? ''),
                bin2hex(random_bytes(24)),
                cuser()['id'],
            ]);
        $invId = (int)$pdo->lastInsertId();
        log_action($pdo, 'create', 'invoice', $invId, 'إصدار فاتورة ' . $number);
        flash('تم إصدار الفاتورة ' . $number . '.');
        redirect('invoice.php?id=' . $invId);
    }
}

$filter = $_GET['status'] ?? '';
$sql = 'SELECT i.*, c.name AS clinic_name FROM invoices i JOIN clinics c ON c.id = i.clinic_id WHERE 1=1';
$args = [];
if (array_key_exists($filter, INV_STATUS)) {
    $sql .= ' AND i.status = ?';
    $args[] = $filter;
}
$sql .= ' ORDER BY i.id DESC LIMIT 200';
$st = $pdo->prepare($sql);
$st->execute($args);
$invoices = $st->fetchAll();

$totals = $pdo->query("SELECT COALESCE(SUM(amount),0) t, COALESCE(SUM(paid),0) p
                       FROM invoices WHERE status <> 'void'")->fetch();

$clinics = $pdo->query('SELECT c.id, c.name, c.plan_id, p.name AS plan_name, p.months, p.price
                        FROM clinics c LEFT JOIN plans p ON p.id = c.plan_id ORDER BY c.name')->fetchAll();
$preClinic = (int)($_GET['clinic'] ?? 0);

page_header('الفواتير', 'invoices.php');
?>
<div class="stats">
    <div class="stat"><div class="label">إجمالي الفواتير</div><div class="value"><?= e(money($totals['t'])) ?></div></div>
    <div class="stat"><div class="label">المحصَّل</div><div class="value"><?= e(money($totals['p'])) ?></div></div>
    <div class="stat bad"><div class="label">المتبقي</div><div class="value"><?= e(money((float)$totals['t'] - (float)$totals['p'])) ?></div></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>🧾 الفواتير</h2>
        <a class="btn" href="invoices.php?new=1">+ فاتورة جديدة</a>
    </div>
    <div class="tabs">
        <a href="invoices.php" class="<?= $filter === '' ? 'active' : '' ?>">الكل</a>
        <?php foreach (INV_STATUS as $k => $l): ?>
            <a href="invoices.php?status=<?= e($k) ?>" class="<?= $filter === $k ? 'active' : '' ?>"><?= e($l) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>الرقم</th><th>العيادة</th><th>التاريخ</th><th>الاستحقاق</th><th>المبلغ</th><th>المتبقي</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): $rem = (float)$i['amount'] - (float)$i['paid']; ?>
            <tr>
                <td dir="ltr"><?= e($i['number']) ?></td>
                <td><a href="clinic.php?id=<?= (int)$i['clinic_id'] ?>"><?= e($i['clinic_name']) ?></a></td>
                <td class="num"><?= e(fmt_date($i['issue_date'])) ?></td>
                <td class="num"><?= e(fmt_date($i['due_date'])) ?>
                    <?php if ($i['due_date'] && $i['due_date'] < date('Y-m-d') && in_array($i['status'], ['unpaid','partial'], true)): ?>
                        <br><span class="badge bad">متأخرة</span>
                    <?php endif; ?></td>
                <td class="num"><?= e(money($i['amount'])) ?></td>
                <td class="num"><?= $rem > 0.005 ? '<strong>' . e(money($rem)) . '</strong>' : '—' ?></td>
                <td><span class="badge <?= e(INV_STATUS_BADGE[$i['status']]) ?>"><?= e(INV_STATUS[$i['status']]) ?></span></td>
                <td><a class="btn btn-sm" href="invoice.php?id=<?= (int)$i['id'] ?>">فتح</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$invoices): ?><tr><td colspan="8" class="muted">لا توجد فواتير.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php if (isset($_GET['new'])): ?>
<div class="card">
    <h2>فاتورة جديدة</h2>
    <form method="post" id="inv-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="grid2">
            <label>العيادة *
                <select name="clinic_id" id="clinic-sel" required>
                    <option value="">— اختر —</option>
                    <?php foreach ($clinics as $cl): ?>
                        <option value="<?= (int)$cl['id'] ?>"
                            data-plan="<?= e($cl['plan_name'] ?? '') ?>"
                            data-months="<?= (int)($cl['months'] ?? 0) ?>"
                            data-price="<?= e($cl['price'] ?? '') ?>"
                            <?= $preClinic === (int)$cl['id'] ? 'selected' : '' ?>>
                            <?= e($cl['name']) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label>وصف الاشتراك <input name="plan_name" id="plan-name" placeholder="سنوي"></label>
        </div>
        <div class="grid3">
            <label>المبلغ * <input type="number" step="0.01" min="0" name="amount" id="amount" required></label>
            <label>عدد الشهور التي يمدّها السداد
                <input type="number" min="0" name="months" id="months" value="1">
                <small class="muted">صفر = فاتورة لا تمدّ الاشتراك (خدمة إضافية مثلًا)</small></label>
            <label>تاريخ الإصدار <input type="date" name="issue_date" value="<?= date('Y-m-d') ?>"></label>
        </div>
        <div class="grid2">
            <label>تاريخ الاستحقاق <input type="date" name="due_date" value="<?= date('Y-m-d', strtotime('+7 days')) ?>"></label>
            <label>ملاحظات <input name="notes"></label>
        </div>
        <div class="actions">
            <button class="btn" type="submit">إصدار الفاتورة</button>
            <a class="btn btn-light" href="invoices.php">إلغاء</a>
        </div>
    </form>
</div>
<script>
// تعبئة المبلغ والمدة من خطة العيادة المختارة، مع إبقاء التعديل اليدوي ممكنًا
document.getElementById('clinic-sel').addEventListener('change', function () {
    var o = this.options[this.selectedIndex];
    if (!o || !o.dataset.price) return;
    document.getElementById('amount').value = o.dataset.price;
    document.getElementById('months').value = o.dataset.months;
    document.getElementById('plan-name').value = o.dataset.plan;
});
document.getElementById('clinic-sel').dispatchEvent(new Event('change'));
</script>
<?php endif; ?>
<?php page_footer();
