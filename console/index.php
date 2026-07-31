<?php
/** لوحة تحكم الكونسول — حالة الاشتراكات والتحصيل في صفحة واحدة. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$warnDate = date('Y-m-d', strtotime('+' . EXPIRY_WARN_DAYS . ' days'));

$byStatus = [];
foreach ($pdo->query('SELECT status, COUNT(*) n FROM clinics GROUP BY status') as $r) {
    $byStatus[$r['status']] = (int)$r['n'];
}
$totalClinics = array_sum($byStatus);

// الدخل الشهري المتكرر: سعر خطة كل عيادة مشتركة موزّعًا على شهورها
$mrr = (float)$pdo->query(
    "SELECT COALESCE(SUM(p.price / GREATEST(p.months,1)), 0)
     FROM clinics c JOIN plans p ON p.id = c.plan_id
     WHERE c.status = 'active'"
)->fetchColumn();

$st = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments
                     WHERE status = 'confirmed' AND pdate >= ?");
$st->execute([$monthStart]);
$monthCollected = (float)$st->fetchColumn();

$outstanding = (float)$pdo->query(
    "SELECT COALESCE(SUM(amount - paid),0) FROM invoices WHERE status IN ('unpaid','partial')"
)->fetchColumn();

$pendingPays = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();

// اشتراكات على وشك الانتهاء أو منتهية
$st = $pdo->prepare(
    "SELECT c.*, p.name AS plan_name FROM clinics c LEFT JOIN plans p ON p.id = c.plan_id
     WHERE c.status IN ('trial','active') AND c.expires_at IS NOT NULL AND c.expires_at <= ?
     ORDER BY c.expires_at"
);
$st->execute([$warnDate]);
$expiring = $st->fetchAll();

$overdue = $pdo->query(
    "SELECT i.*, c.name AS clinic_name FROM invoices i JOIN clinics c ON c.id = i.clinic_id
     WHERE i.status IN ('unpaid','partial') AND i.due_date IS NOT NULL AND i.due_date < CURDATE()
     ORDER BY i.due_date LIMIT 15"
)->fetchAll();

$recentPays = $pdo->query(
    "SELECT pm.*, c.name AS clinic_name, i.number FROM payments pm
     JOIN clinics c ON c.id = pm.clinic_id
     LEFT JOIN invoices i ON i.id = pm.invoice_id
     ORDER BY pm.id DESC LIMIT 10"
)->fetchAll();

// عيادات لم تُرسل نبضة منذ أكثر من 3 أيام رغم أنها مشتركة
$silent = $pdo->query(
    "SELECT name, last_ping_at FROM clinics
     WHERE status = 'active'
       AND (last_ping_at IS NULL OR last_ping_at < DATE_SUB(NOW(), INTERVAL 3 DAY))
     ORDER BY last_ping_at IS NOT NULL, last_ping_at LIMIT 10"
)->fetchAll();

page_header('لوحة التحكم', 'index.php');
?>
<div class="stats">
    <div class="stat accent"><div class="label">دخل شهري متكرر (MRR)</div><div class="value"><?= e(money($mrr)) ?></div></div>
    <div class="stat"><div class="label">تحصيل هذا الشهر</div><div class="value"><?= e(money($monthCollected)) ?></div></div>
    <div class="stat <?= $outstanding > 0.005 ? 'bad' : '' ?>"><div class="label">مستحق غير محصَّل</div><div class="value"><?= e(money($outstanding)) ?></div></div>
    <div class="stat"><div class="label">إجمالي العيادات</div><div class="value"><?= $totalClinics ?></div></div>
</div>

<div class="stats">
    <?php foreach (CLINIC_STATUS as $k => $label): ?>
        <div class="stat"><div class="label"><?= e($label) ?></div>
            <div class="value"><?= (int)($byStatus[$k] ?? 0) ?></div></div>
    <?php endforeach; ?>
</div>

<?php if ($pendingPays): ?>
<div class="alert alert-warning">
    <strong><?= $pendingPays ?></strong> دفعة بانتظار تأكيدك (تحويلات إنستا باي غالبًا).
    <a href="payments.php?status=pending">راجعها الآن ←</a>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>⏳ اشتراكات تحتاج تجديد</h2>
        <span class="muted">خلال <?= EXPIRY_WARN_DAYS ?> يومًا أو منتهية بالفعل</span>
    </div>
    <?php if (!$expiring): ?>
        <p class="muted">لا يوجد اشتراك قارب على الانتهاء. 👌</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>العيادة</th><th>الخطة</th><th>ينتهي في</th><th>المتبقي</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($expiring as $c): $d = days_until($c['expires_at']); ?>
            <tr>
                <td><a href="clinic.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['name']) ?></strong></a><br>
                    <small class="muted"><?= e($c['owner_name']) ?></small></td>
                <td><?= e($c['plan_name'] ?? '—') ?></td>
                <td class="num"><?= e(fmt_date($c['expires_at'])) ?></td>
                <td class="num">
                    <?php if ($d < 0): ?><span class="badge bad">منتهٍ من <?= abs($d) ?> يوم</span>
                    <?php elseif ($d === 0): ?><span class="badge bad">ينتهي اليوم</span>
                    <?php else: ?><span class="badge warn"><?= $d ?> يوم</span><?php endif; ?>
                </td>
                <td><a class="btn btn-sm" href="invoices.php?new=1&clinic=<?= (int)$c['id'] ?>">إصدار فاتورة تجديد</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php if ($overdue): ?>
<div class="card">
    <h2>🔴 فواتير متأخرة</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الفاتورة</th><th>العيادة</th><th>الاستحقاق</th><th>المتبقي</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($overdue as $i): ?>
            <tr>
                <td dir="ltr"><?= e($i['number']) ?></td>
                <td><a href="clinic.php?id=<?= (int)$i['clinic_id'] ?>"><?= e($i['clinic_name']) ?></a></td>
                <td class="num"><?= e(fmt_date($i['due_date'])) ?>
                    <span class="badge bad">متأخرة <?= abs((int)days_until($i['due_date'])) ?> يوم</span></td>
                <td class="num"><strong><?= e(money((float)$i['amount'] - (float)$i['paid'])) ?></strong></td>
                <td><a class="btn btn-sm" href="invoice.php?id=<?= (int)$i['id'] ?>">فتح</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="grid2">
    <div class="card">
        <h2>💳 آخر المدفوعات</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>العيادة</th><th>المبلغ</th><th>الوسيلة</th><th>الحالة</th></tr></thead>
            <tbody>
            <?php foreach ($recentPays as $p): ?>
                <tr>
                    <td><?= e($p['clinic_name']) ?><br><small class="muted"><?= e(fmt_date($p['pdate'])) ?></small></td>
                    <td class="num"><?= e(money($p['amount'])) ?></td>
                    <td><?= e(PAY_METHODS[$p['method']] ?? $p['method']) ?></td>
                    <td><span class="badge <?= e(PAY_STATUS_BADGE[$p['status']]) ?>"><?= e(PAY_STATUS[$p['status']]) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentPays): ?><tr><td colspan="4" class="muted">لا توجد مدفوعات بعد.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <h2>📡 عيادات صامتة</h2>
        <p class="muted">مشتركة لكن نسختها لم تتصل منذ 3 أيام أو أكثر — قد تكون متوقفة أو الرابط تغيّر.</p>
        <div class="table-wrap"><table>
            <thead><tr><th>العيادة</th><th>آخر اتصال</th></tr></thead>
            <tbody>
            <?php foreach ($silent as $s): ?>
                <tr>
                    <td><?= e($s['name']) ?></td>
                    <td class="num"><?= $s['last_ping_at'] ? e(fmt_date($s['last_ping_at'])) : '<span class="badge muted">لم تتصل أبدًا</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$silent): ?><tr><td colspan="2" class="muted">كل النسخ متصلة. 👌</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div>
</div>
<?php page_footer();
