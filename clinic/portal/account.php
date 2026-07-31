<?php
require __DIR__ . '/inc/bootstrap.php';
portal_require_login();
$p = portal_load($pdo);
$id = (int)$p['id'];

refresh_package_status($pdo);

$st = $pdo->prepare(
    'SELECT i.*, d.name AS drug_name FROM injection_doses i JOIN drugs d ON d.id = i.drug_id
     WHERE i.patient_id = ? ORDER BY i.dose_date DESC, i.id DESC'
);
$st->execute([$id]);
$doses = $st->fetchAll();

$st = $pdo->prepare(
    'SELECT pp.*, (SELECT COUNT(*) FROM package_uses u WHERE u.patient_package_id = pp.id) AS used
     FROM patient_packages pp WHERE pp.patient_id = ? ORDER BY pp.status = "active" DESC, pp.id DESC'
);
$st->execute([$id]);
$pkgs = $st->fetchAll();

$st = $pdo->prepare('SELECT * FROM payments WHERE patient_id = ? ORDER BY pdate DESC, id DESC LIMIT 30');
$st->execute([$id]);
$pays = $st->fetchAll();

$injBalance = injection_balance($pdo, $id);
$pkgOwed = array_sum(array_map(
    fn($r) => $r['status'] !== 'cancelled' ? (float)$r['price'] - (float)$r['paid'] : 0, $pkgs));
$totalDue = round($injBalance + $pkgOwed, 2);
$totalPaid = array_sum(array_map(fn($r) => (float)$r['amount'], $pays));

/*
 * زر الدفع يفتح مطالبة قائمة إن وُجدت، وإلا ينشئ واحدة بقيمة المستحق. لا
 * تُنشأ مطالبة جديدة مع كل فتح للصفحة حتى لا تتكدس مطالبات لنفس المبلغ.
 */
require_once dirname(__DIR__) . '/inc/paymob.php';
$payUrl = '';
if ($totalDue > 0.005 && (clinic_paymob_ready() || setting('clinic_instapay') !== '')) {
    $st = $pdo->prepare("SELECT token FROM payment_requests
                         WHERE patient_id = ? AND status = 'pending' AND amount = ?
                           AND (expires_at IS NULL OR expires_at > NOW())
                         ORDER BY id DESC LIMIT 1");
    $st->execute([$id, $totalDue]);
    if ($tok = $st->fetchColumn()) {
        $payUrl = '../paylink.php?t=' . $tok;
    } else {
        $tok = bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO payment_requests (patient_id, amount, description, token, expires_at)
                       VALUES (?,?,?,?,DATE_ADD(NOW(), INTERVAL 7 DAY))')
            ->execute([$id, $totalDue, 'مستحقات العيادة', $tok]);
        $payUrl = '../paylink.php?t=' . $tok;
    }
}

portal_header('حسابي', 'account.php');
?>
<div class="pgrid">
    <div class="pstat <?= $totalDue > 0.005 ? 'pstat-due' : '' ?>">
        <div class="plabel">المستحق عليك</div>
        <div class="pvalue" style="color:<?= $totalDue > 0.005 ? '#b91c1c' : '#15803d' ?>">
            <?= $totalDue > 0.005 ? e(money($totalDue)) : 'لا مستحقات ✔' ?></div>
        <?php if ($payUrl): ?>
            <a class="pbtn" href="<?= e($payUrl) ?>" style="margin-top:10px;display:block;text-align:center">
                💳 ادفع الآن</a>
        <?php endif; ?>
    </div>
    <div class="pstat">
        <div class="plabel">إجمالي ما سددته</div>
        <div class="pvalue"><?= e(money($totalPaid)) ?></div>
    </div>
</div>

<?php if ($pkgs): ?>
<div class="pcard">
    <div class="plabel">🎟️ باقاتي</div>
    <div class="ptable-wrap"><table class="ptable">
        <thead><tr><th>الباقة</th><th>الجلسات</th><th>تنتهي</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php foreach ($pkgs as $pk): $left = (int)$pk['sessions_total'] - (int)$pk['used']; ?>
            <tr>
                <td><?= e($pk['name']) ?></td>
                <td><strong><?= $left ?></strong> من <?= (int)$pk['sessions_total'] ?></td>
                <td><?= e(fmt_date($pk['expiry_date'])) ?></td>
                <td><span class="pbadge <?= e(PKG_BADGE[$pk['status']]) ?>"><?= e(PKG_STATUS[$pk['status']]) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<?php if ($doses): ?>
<div class="pcard">
    <div class="plabel">💉 جرعات الحقن</div>
    <div class="ptable-wrap"><table class="ptable">
        <thead><tr><th>التاريخ</th><th>الدواء</th><th>الوحدات</th><th>المستحق</th><th>المدفوع</th></tr></thead>
        <tbody>
        <?php foreach ($doses as $r): $rest = (float)$r['amount'] - (float)$r['paid']; ?>
            <tr>
                <td><?= e(fmt_date($r['dose_date'])) ?></td>
                <td><?= e($r['drug_name']) ?></td>
                <td><strong><?= e(num_fmt($r['units'])) ?></strong></td>
                <td><?= e(money($r['amount'])) ?></td>
                <td><?= $rest > 0.005
                    ? '<span class="pbadge bad">متبقٍ ' . e(money($rest)) . '</span>'
                    : '<span class="pbadge ok">مسدَّد</span>' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<div class="pcard">
    <div class="plabel">💳 سجل المدفوعات</div>
    <?php if (!$pays): ?>
        <p class="pmuted">لا توجد مدفوعات مسجّلة.</p>
    <?php else: ?>
    <div class="ptable-wrap"><table class="ptable">
        <thead><tr><th>التاريخ</th><th>الخدمة</th><th>المبلغ</th></tr></thead>
        <tbody>
        <?php foreach ($pays as $r): ?>
            <tr>
                <td><?= e(fmt_date($r['pdate'])) ?></td>
                <td><?= e($r['service'] ?: '—') ?></td>
                <td><strong><?= e(money($r['amount'])) ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<?php if (setting('clinic_phone')): ?>
<a class="pbtn pbtn-block" href="tel:<?= e(setting('clinic_phone')) ?>">📞 اتصل بالعيادة</a>
<?php endif; ?>
<?php portal_footer();
