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

portal_header('حسابي', 'account.php');
?>
<div class="pgrid">
    <div class="pstat <?= $totalDue > 0.005 ? 'pstat-due' : '' ?>">
        <div class="plabel">المستحق عليك</div>
        <div class="pvalue" style="color:<?= $totalDue > 0.005 ? '#b91c1c' : '#15803d' ?>">
            <?= $totalDue > 0.005 ? e(money($totalDue)) : 'لا مستحقات ✔' ?></div>
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
