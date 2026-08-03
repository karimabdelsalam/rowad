<?php
/**
 * التحصيل الأونلاين: إنشاء روابط دفع للمرضى ومتابعة ما تحصَّل.
 *
 * التحصيل يتم على حساب العيادة نفسها (باي موب / إنستا باي الخاصين بها)،
 * فما يُسجَّل هنا هو ما دخل حساب العيادة مباشرةً.
 */
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('pay.view');
require_once __DIR__ . '/inc/paymob.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        deny_unless('pay.create', 'collect.php');
        $pid = posted_patient_id($pdo);
        $pat = null;
        if ($pid) {
            $q = $pdo->prepare('SELECT id, doctor_id FROM patients WHERE id = ?');
            $q->execute([$pid]);
            $pat = $q->fetch() ?: null;
        }
        // الطبيب المقيَّد لا ينشئ مطالبة لمريض ليس تحت إشرافه
        if (!$pat || !can_access_patient($pat)) {
            flash('اختر المريض من قائمة البحث.', 'danger');
            redirect('collect.php');
        }
        $amount = round((float)($_POST['amount'] ?? 0), 2);
        if ($amount < 1) {
            flash('أقل مبلغ للدفع الأونلاين جنيه واحد.', 'danger');
            redirect('collect.php?patient=' . $pid);
        }
        $days = max(1, min(30, (int)($_POST['valid_days'] ?? 7)));
        $pdo->prepare('INSERT INTO payment_requests (patient_id, amount, description, token, expires_at, created_by)
                       VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL ? DAY),?)')
            ->execute([
                $pid, $amount, trim($_POST['description'] ?? '') ?: 'مستحقات العيادة',
                bin2hex(random_bytes(24)), $days, user()['id'],
            ]);
        $rid = (int)$pdo->lastInsertId();
        activity($pdo, 'create', 'payreq', $rid, 'طلب دفع ' . money($amount));
        flash('تم إنشاء رابط الدفع — انسخه أو أرسله واتساب.');
        redirect('collect.php?new=' . $rid);
    }

    if ($action === 'cancel') {
        deny_unless('pay.create', 'collect.php');
        $rid = (int)($_POST['rid'] ?? 0);
        $pdo->prepare("UPDATE payment_requests SET status = 'cancelled' WHERE id = ? AND status = 'pending'")
            ->execute([$rid]);
        activity($pdo, 'update', 'payreq', $rid, 'إلغاء طلب دفع');
        flash('أُلغي طلب الدفع، ولم يعد رابطه صالحًا.', 'warning');
        redirect('collect.php');
    }
}

[$df, $dfArgs] = doctor_filter('p');
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

$st = $pdo->prepare(
    "SELECT r.*, p.name AS pname, p.phone FROM payment_requests r
     JOIN patients p ON p.id = r.patient_id
     WHERE DATE(r.created_at) BETWEEN ? AND ? $df ORDER BY r.id DESC LIMIT 200"
);
$st->execute([$from, $to, ...$dfArgs]);
$reqs = $st->fetchAll();

// ملخص التحصيل بالوسيلة من جدول المدفوعات نفسه — لا من طلبات الدفع وحدها
$st = $pdo->prepare('SELECT method, COALESCE(SUM(amount),0) t, COUNT(*) n FROM payments
                     WHERE pdate BETWEEN ? AND ? GROUP BY method');
$st->execute([$from, $to]);
$byMethod = [];
$grand = 0.0;
foreach ($st as $r) {
    $byMethod[$r['method']] = ['t' => (float)$r['t'], 'n' => (int)$r['n']];
    $grand += (float)$r['t'];
}

$onlineTotal = 0.0;
$pendingTotal = 0.0;
foreach ($reqs as $r) {
    if ($r['status'] === 'paid') {
        $onlineTotal += (float)$r['amount'];
    } elseif ($r['status'] === 'pending') {
        $pendingTotal += (float)$r['amount'];
    }
}

$newReq = null;
if ($rid = (int)($_GET['new'] ?? 0)) {
    $st = $pdo->prepare('SELECT r.*, p.name AS pname, p.phone FROM payment_requests r
                         JOIN patients p ON p.id = r.patient_id WHERE r.id = ?');
    $st->execute([$rid]);
    $newReq = $st->fetch() ?: null;
}

$prefPatient = (int)($_GET['patient'] ?? 0) ?: null;
$prefDue = $prefPatient ? patient_due($pdo, $prefPatient) : 0.0;
$ready = clinic_paymob_ready();
$instapay = setting('clinic_instapay');

page_header('التحصيل الأونلاين', 'collect.php');
?>
<?php if (!$ready && $instapay === ''): ?>
<div class="alert alert-warning">
    لم تُضبط وسيلة تحصيل أونلاين بعد. من <a href="settings.php">الإعدادات ← التحصيل الأونلاين</a>
    اربط حساب باي موب الخاص بعيادتك أو أضف عنوان إنستا باي — <strong>التحصيل يدخل حسابك مباشرةً</strong>.
</div>
<?php endif; ?>

<?php if ($newReq): $url = payreq_url($newReq['token']);
    $waPhone = wa_phone($newReq['phone']);
    $msg = 'مرحبًا ' . $newReq['pname'] . " 🌿\n" . $newReq['description'] . ': ' . money($newReq['amount'])
         . "\nللدفع: " . $url; ?>
<div class="card" style="border-color:#0f766e">
    <h2>🔗 رابط الدفع جاهز</h2>
    <p class="muted"><?= e($newReq['pname']) ?> — <strong><?= e(money($newReq['amount'])) ?></strong></p>
    <label>الرابط <input value="<?= e($url) ?>" dir="ltr" readonly onclick="this.select()"></label>
    <div class="actions">
        <?php if ($waPhone): ?>
            <a class="btn" target="_blank" rel="noopener"
               href="https://wa.me/<?= e($waPhone) ?>?text=<?= rawurlencode($msg) ?>">📱 إرسال بواتساب</a>
        <?php endif; ?>
        <a class="btn btn-light" href="collect.php">تم</a>
    </div>
</div>
<?php endif; ?>

<div class="stats">
    <div class="stat accent"><div class="label">إجمالي التحصيل في الفترة</div><div class="value"><?= e(money($grand)) ?></div></div>
    <div class="stat"><div class="label">تحصيل أونلاين</div><div class="value"><?= e(money($onlineTotal)) ?></div></div>
    <div class="stat"><div class="label">روابط بانتظار الدفع</div><div class="value"><?= e(money($pendingTotal)) ?></div></div>
</div>

<?php if (can('pay.create')): ?>
<div class="card">
    <h2>➕ طلب دفع جديد</h2>
    <p class="muted">اختر المريض فيظهر المتبقي عليه، ثم أرسل له الرابط — يدفع من موبايله
        بالفيزا أو يرى بيانات إنستا باي.</p>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="grid4">
            <label>المريض * <?= patient_picker($pdo, 'patient_id', $prefPatient) ?></label>
            <label>المبلغ *
                <input type="number" step="0.01" min="1" name="amount" required
                       value="<?= $prefDue > 0 ? e(number_format($prefDue, 2, '.', '')) : '' ?>">
                <?php if ($prefPatient): ?>
                    <small class="muted">المتبقي على المريض: <?= e(money($prefDue)) ?></small>
                <?php endif; ?></label>
            <label>البيان <input name="description" placeholder="مستحقات العيادة"></label>
            <label>صلاحية الرابط
                <input type="number" min="1" max="30" name="valid_days" value="7">
                <small class="muted">أيام</small></label>
        </div>
        <div class="actions"><button class="btn" type="submit">إنشاء رابط الدفع</button></div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>📊 التحصيل بالوسيلة</h2>
        <form class="inline-form" method="get" style="margin:0">
            <label>من <input type="date" name="from" value="<?= e($from) ?>"></label>
            <label>إلى <input type="date" name="to" value="<?= e($to) ?>"></label>
            <button class="btn btn-light btn-sm" type="submit">عرض</button>
        </form>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>الوسيلة</th><th>عدد العمليات</th><th>الإجمالي</th></tr></thead>
        <tbody>
        <?php foreach (PAY_METHODS as $k => $label): $row = $byMethod[$k] ?? null; ?>
            <tr>
                <td><?= e($label) ?></td>
                <td class="num"><?= $row ? (int)$row['n'] : 0 ?></td>
                <td class="num"><?= e(money($row['t'] ?? 0)) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr><td><strong>الإجمالي</strong></td><td></td>
                <td class="num"><strong><?= e(money($grand)) ?></strong></td></tr>
        </tbody>
    </table></div>
    <p class="muted">هذه أرقام ما سُجِّل في العيادة. طابقها مع كشف حسابك في بوابة الدفع
        وحساب إنستا باي للتأكد من وصول كل مبلغ.</p>
</div>

<div class="card">
    <h2>🔗 روابط الدفع</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>المريض</th><th>المبلغ</th><th>البيان</th><th>أُنشئ</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($reqs as $r):
            $expired = $r['status'] === 'pending' && $r['expires_at'] && $r['expires_at'] < date('Y-m-d H:i:s'); ?>
            <tr>
                <td><a href="patient.php?id=<?= (int)$r['patient_id'] ?>"><?= e($r['pname']) ?></a></td>
                <td class="num"><strong><?= e(money($r['amount'])) ?></strong></td>
                <td><?= e($r['description']) ?></td>
                <td class="num"><small><?= e(date('Y/m/d', strtotime($r['created_at']))) ?></small></td>
                <td>
                    <?php if ($r['status'] === 'paid'): ?>
                        <span class="badge ok">مدفوع</span>
                        <?php if ($r['method']): ?><br><small class="muted"><?= e(PAY_METHODS[$r['method']] ?? $r['method']) ?></small><?php endif; ?>
                    <?php elseif ($r['status'] === 'cancelled'): ?><span class="badge muted">ملغي</span>
                    <?php elseif ($r['status'] === 'failed'): ?><span class="badge bad">فشل</span>
                    <?php elseif ($expired): ?><span class="badge muted">منتهي</span>
                    <?php else: ?><span class="badge warn">بانتظار الدفع</span><?php endif; ?>
                </td>
                <td><div class="actions">
                    <?php if ($r['status'] === 'pending' && !$expired): ?>
                        <?php $u = payreq_url($r['token']); $wp = wa_phone($r['phone']); ?>
                        <?php if ($wp): ?>
                            <a class="btn btn-sm" target="_blank" rel="noopener"
                               href="https://wa.me/<?= e($wp) ?>?text=<?= rawurlencode($r['description'] . ': ' . money($r['amount']) . "\n" . $u) ?>">📱</a>
                        <?php endif; ?>
                        <?php if (can('pay.create')): ?>
                        <form method="post" data-confirm="إلغاء رابط الدفع؟">
                            <?= csrf_field() ?><input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit">إلغاء</button>
                        </form>
                        <?php endif; ?>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$reqs): ?><tr><td colspan="6" class="muted">لا توجد روابط دفع في هذه الفترة.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
