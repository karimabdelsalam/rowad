<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('exp.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        deny_unless('exp.manage', 'expenses.php');
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            flash('أدخل مبلغًا صحيحًا.', 'danger');
            redirect('expenses.php');
        }
        $cat = array_key_exists($_POST['category'] ?? '', EXPENSE_CATS) ? $_POST['category'] : 'other';
        $st = $pdo->prepare('INSERT INTO expenses (edate, category, amount, notes, created_by) VALUES (?,?,?,?,?)');
        $st->execute([
            ($_POST['edate'] ?? '') ?: date('Y-m-d'),
            $cat,
            $amount,
            trim($_POST['notes'] ?? ''),
            user()['id'],
        ]);
        flash('تم تسجيل المصروف.');
        redirect('expenses.php');
    }

    if ($action === 'delete') {
        deny_unless('exp.manage', 'expenses.php');
        $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([(int)$_POST['eid']]);
        flash('تم حذف المصروف.');
        redirect('expenses.php');
    }
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$validDate = fn(string $d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d);
if (!$validDate($from)) $from = date('Y-m-01');
if (!$validDate($to)) $to = date('Y-m-d');

$st = $pdo->prepare('SELECT * FROM expenses WHERE edate BETWEEN ? AND ? ORDER BY edate DESC, id DESC');
$st->execute([$from, $to]);
$rows = $st->fetchAll();
$sum = array_sum(array_map(fn($r) => (float)$r['amount'], $rows));

page_header('المصروفات', 'expenses.php');
?>
<div class="card">
    <h2>تسجيل مصروف</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <div class="grid4">
            <label>التاريخ <input type="date" name="edate" value="<?= date('Y-m-d') ?>" required></label>
            <label>البند
                <select name="category">
                    <?php foreach (EXPENSE_CATS as $k => $v): ?><option value="<?= e($k) ?>"><?= e($v) ?></option><?php endforeach; ?>
                </select>
            </label>
            <label>المبلغ * <input type="number" step="0.01" min="0.01" name="amount" required></label>
            <label>ملاحظات <input name="notes"></label>
        </div>
        <button class="btn" type="submit">حفظ المصروف</button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h2>🧾 المصروفات من <?= e(fmt_date($from)) ?> إلى <?= e(fmt_date($to)) ?></h2>
        <form class="inline-form" method="get">
            <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="btn btn-light btn-sm" type="submit">عرض</button>
            <a class="btn btn-xls btn-sm" href="export.php?type=expenses&from=<?= e($from) ?>&to=<?= e($to) ?>">⬇ تصدير Excel</a>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>البند</th><th>المبلغ</th><th>ملاحظات</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td class="num"><?= e(fmt_date($r['edate'])) ?></td>
                <td><?= e(EXPENSE_CATS[$r['category']] ?? $r['category']) ?></td>
                <td class="num"><strong><?= e(money($r['amount'])) ?></strong></td>
                <td><?= e($r['notes']) ?></td>
                <td>
                    <form method="post" data-confirm="حذف هذا المصروف؟">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                        <input type="hidden" name="eid" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-light btn-sm" type="submit">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="5" class="muted">لا توجد مصروفات في هذه الفترة.</td></tr><?php endif; ?>
        </tbody>
        <tfoot><tr><td>الإجمالي</td><td class="num" colspan="4"><?= e(money($sum)) ?></td></tr></tfoot>
    </table></div>
</div>
<?php page_footer();
