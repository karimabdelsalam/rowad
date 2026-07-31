<?php
/** فاتورة واحدة: تسجيل الدفعات، تأكيدها، وإرسال رابط السداد للعميل. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$load = function () use ($pdo, $id) {
    $st = $pdo->prepare('SELECT i.*, c.name AS clinic_name, c.phone, c.expires_at
                         FROM invoices i JOIN clinics c ON c.id = i.clinic_id WHERE i.id = ?');
    $st->execute([$id]);
    return $st->fetch();
};
$inv = $load();
if (!$inv) {
    flash('الفاتورة غير موجودة.', 'danger');
    redirect('invoices.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_payment') {
        $amount = (float)($_POST['amount'] ?? 0);
        if ($amount <= 0) {
            flash('أدخل مبلغًا أكبر من صفر.', 'danger');
            redirect('invoice.php?id=' . $id);
        }
        $method = array_key_exists($_POST['method'] ?? '', PAY_METHODS) ? $_POST['method'] : 'cash';
        // التحويل يُسجَّل بانتظار التأكيد حتى تراجع وصوله فعليًا لحسابك
        $status = ($method === 'instapay' && isset($_POST['unconfirmed'])) ? 'pending' : 'confirmed';

        $pdo->beginTransaction();
        try {
            $pdo->prepare('INSERT INTO payments (invoice_id, clinic_id, pdate, amount, method, status,
                           reference, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
                ->execute([
                    $id, (int)$inv['clinic_id'],
                    ($_POST['pdate'] ?? '') ?: date('Y-m-d'),
                    $amount, $method, $status,
                    trim($_POST['reference'] ?? ''),
                    trim($_POST['notes'] ?? ''),
                    cuser()['id'],
                ]);
            $payId = (int)$pdo->lastInsertId();
            invoice_recalc($pdo, $id);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('تعذر تسجيل الدفعة: ' . $ex->getMessage(), 'danger');
            redirect('invoice.php?id=' . $id);
        }
        log_action($pdo, 'create', 'payment', $payId,
            'دفعة ' . money($amount) . ' على ' . $inv['number'] . ' (' . PAY_METHODS[$method] . ')');
        flash($status === 'pending' ? 'سُجّلت الدفعة بانتظار تأكيدك.' : 'تم تسجيل الدفعة.');
        redirect('invoice.php?id=' . $id);
    }

    if ($action === 'confirm_payment' || $action === 'unconfirm_payment') {
        $payId = (int)($_POST['pay_id'] ?? 0);
        $new = $action === 'confirm_payment' ? 'confirmed' : 'pending';
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE payments SET status = ? WHERE id = ? AND invoice_id = ?')
                ->execute([$new, $payId, $id]);
            invoice_recalc($pdo, $id);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('تعذر تحديث الدفعة: ' . $ex->getMessage(), 'danger');
            redirect('invoice.php?id=' . $id);
        }
        log_action($pdo, 'update', 'payment', $payId,
            ($new === 'confirmed' ? 'تأكيد' : 'إلغاء تأكيد') . ' دفعة على ' . $inv['number']);
        flash($new === 'confirmed' ? 'تم تأكيد الدفعة.' : 'أُلغي تأكيد الدفعة.');
        redirect('invoice.php?id=' . $id);
    }

    if ($action === 'delete_payment') {
        $payId = (int)($_POST['pay_id'] ?? 0);
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM payments WHERE id = ? AND invoice_id = ?')->execute([$payId, $id]);
            invoice_recalc($pdo, $id);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('تعذر حذف الدفعة: ' . $ex->getMessage(), 'danger');
            redirect('invoice.php?id=' . $id);
        }
        log_action($pdo, 'delete', 'payment', $payId, 'حذف دفعة من ' . $inv['number']);
        flash('تم حذف الدفعة.', 'warning');
        redirect('invoice.php?id=' . $id);
    }

    if ($action === 'void') {
        $pdo->beginTransaction();
        try {
            // إلغاء فاتورة سبق أن مدّت الاشتراك يسترجع الشهور أولًا
            if ((int)$inv['applied'] && (int)$inv['months'] > 0) {
                clinic_extend($pdo, (int)$inv['clinic_id'], -(int)$inv['months']);
            }
            $pdo->prepare("UPDATE invoices SET status = 'void', applied = 0 WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('تعذر إلغاء الفاتورة: ' . $ex->getMessage(), 'danger');
            redirect('invoice.php?id=' . $id);
        }
        log_action($pdo, 'update', 'invoice', $id, 'إلغاء الفاتورة ' . $inv['number']);
        flash('أُلغيت الفاتورة.', 'warning');
        redirect('invoice.php?id=' . $id);
    }
}

$inv = $load();
$pays = $pdo->prepare('SELECT * FROM payments WHERE invoice_id = ? ORDER BY id');
$pays->execute([$id]);
$pays = $pays->fetchAll();

$remaining = (float)$inv['amount'] - (float)$inv['paid'];
$payUrl = rtrim(setting('console_url', ''), '/') . '/pay.php?t=' . $inv['pay_token'];

page_header('فاتورة ' . $inv['number'], 'invoices.php');
?>
<div class="card">
    <div class="card-head">
        <h2>🧾 <span dir="ltr"><?= e($inv['number']) ?></span>
            <span class="badge <?= e(INV_STATUS_BADGE[$inv['status']]) ?>"><?= e(INV_STATUS[$inv['status']]) ?></span></h2>
        <a class="btn btn-light" href="clinic.php?id=<?= (int)$inv['clinic_id'] ?>">ملف العيادة</a>
    </div>
    <div class="grid4">
        <div><span class="muted">العيادة</span><br><strong><?= e($inv['clinic_name']) ?></strong></div>
        <div><span class="muted">المبلغ</span><br><strong><?= e(money($inv['amount'])) ?></strong></div>
        <div><span class="muted">المسدد</span><br><strong><?= e(money($inv['paid'])) ?></strong></div>
        <div><span class="muted">المتبقي</span><br>
            <strong class="<?= $remaining > 0.005 ? 'text-bad' : '' ?>"><?= e(money(max(0, $remaining))) ?></strong></div>
    </div>
    <div class="grid4" style="margin-top:12px">
        <div><span class="muted">الإصدار</span><br><?= e(fmt_date($inv['issue_date'])) ?></div>
        <div><span class="muted">الاستحقاق</span><br><?= e(fmt_date($inv['due_date'])) ?></div>
        <div><span class="muted">تمدّ الاشتراك</span><br><?= (int)$inv['months'] ?> شهر
            <?= (int)$inv['applied'] ? '<span class="badge ok">طُبِّقت</span>' : '' ?></div>
        <div><span class="muted">اشتراك العيادة ينتهي</span><br><?= e(fmt_date($inv['expires_at'])) ?></div>
    </div>
    <?php if ($inv['notes']): ?><p class="muted" style="margin-top:10px"><?= e($inv['notes']) ?></p><?php endif; ?>
</div>

<?php if ($inv['status'] !== 'void'): ?>
<div class="card">
    <h2>🔗 رابط السداد للعميل</h2>
    <?php if (setting('console_url') === ''): ?>
        <div class="alert alert-warning">اضبط <strong>رابط الكونسول</strong> في الإعدادات أولًا حتى يصبح الرابط صالحًا للإرسال.</div>
    <?php endif; ?>
    <p class="muted">افتحه للعميل ليدفع بنفسه بباي موب أو يرى بيانات التحويل — لا يحتاج حسابًا.</p>
    <div class="copy-row">
        <input value="<?= e($payUrl) ?>" dir="ltr" readonly onclick="this.select()">
    </div>
    <?php if ($inv['phone']): ?>
        <?php
        $waPhone = preg_replace('/\D/', '', $inv['phone']);
        $waPhone = str_starts_with($waPhone, '0') ? '2' . $waPhone : $waPhone;
        $msg = 'فاتورة اشتراك ' . $inv['number'] . ' بمبلغ ' . money($remaining)
             . "\nللسداد: " . $payUrl;
        ?>
        <div class="actions" style="margin-top:10px">
            <a class="btn" target="_blank" rel="noopener"
               href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode($msg) ?>">📱 إرسال بواتساب</a>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>➕ تسجيل دفعة</h2>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_payment">
        <div class="grid4">
            <label>المبلغ * <input type="number" step="0.01" min="0.01" name="amount"
                value="<?= e(number_format(max(0, $remaining), 2, '.', '')) ?>" required></label>
            <label>الوسيلة
                <select name="method" id="method-sel">
                    <?php foreach (PAY_METHODS as $k => $l): ?>
                        <?php if ($k === 'paymob') continue; ?>
                        <option value="<?= e($k) ?>"><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label>التاريخ <input type="date" name="pdate" value="<?= date('Y-m-d') ?>"></label>
            <label>رقم العملية / المرجع <input name="reference" dir="ltr" placeholder="رقم تحويل إنستا باي"></label>
        </div>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="unconfirmed" style="width:auto">
            سجّلها <strong>بانتظار التأكيد</strong> (تحويل لم أتأكد من وصوله بعد)
        </label>
        <label>ملاحظات <input name="notes"></label>
        <div class="actions"><button class="btn" type="submit">تسجيل الدفعة</button></div>
    </form>
    <p class="muted">دفعات <strong>باي موب</strong> تُسجَّل تلقائيًا عند نجاح السداد أونلاين، فلا تُضاف يدويًا من هنا.</p>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>💳 دفعات هذه الفاتورة</h2>
        <?php if ($inv['status'] !== 'void'): ?>
        <form method="post" onsubmit="return confirm('إلغاء الفاتورة؟ سيُسترجع أي مدّ للاشتراك نتج عنها.')">
            <?= csrf_field() ?><input type="hidden" name="action" value="void">
            <button class="btn btn-danger btn-sm" type="submit">إلغاء الفاتورة</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>المبلغ</th><th>الوسيلة</th><th>المرجع</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?>
            <tr>
                <td class="num"><?= e(fmt_date($p['pdate'])) ?></td>
                <td class="num"><strong><?= e(money($p['amount'])) ?></strong></td>
                <td><?= e(PAY_METHODS[$p['method']] ?? $p['method']) ?></td>
                <td dir="ltr"><small><?= e($p['reference'] ?: '—') ?>
                    <?php if ($p['gateway_txn_id']): ?><br>txn: <?= e($p['gateway_txn_id']) ?><?php endif; ?></small></td>
                <td><span class="badge <?= e(PAY_STATUS_BADGE[$p['status']]) ?>"><?= e(PAY_STATUS[$p['status']]) ?></span></td>
                <td><div class="actions">
                    <?php if ($p['status'] === 'pending'): ?>
                        <form method="post"><?= csrf_field() ?>
                            <input type="hidden" name="action" value="confirm_payment">
                            <input type="hidden" name="pay_id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm" type="submit">تأكيد الوصول</button></form>
                    <?php elseif ($p['status'] === 'confirmed'): ?>
                        <form method="post" onsubmit="return confirm('إلغاء تأكيد الدفعة؟ قد يتراجع مدّ الاشتراك.')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="unconfirm_payment">
                            <input type="hidden" name="pay_id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit">إلغاء التأكيد</button></form>
                    <?php endif; ?>
                    <form method="post" onsubmit="return confirm('حذف الدفعة نهائيًا؟')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_payment">
                        <input type="hidden" name="pay_id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button></form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pays): ?><tr><td colspan="6" class="muted">لا توجد دفعات بعد.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
