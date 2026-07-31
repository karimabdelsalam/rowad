<?php
/** ملف عيادة واحدة: الاشتراك، الفواتير، المدفوعات، ورمز الربط. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare('SELECT c.*, p.name AS plan_name, p.months AS plan_months, p.price AS plan_price
                     FROM clinics c LEFT JOIN plans p ON p.id = c.plan_id WHERE c.id = ?');
$st->execute([$id]);
$c = $st->fetch();
if (!$c) {
    flash('العيادة غير موجودة.', 'danger');
    redirect('clinics.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (($_POST['action'] ?? '') === 'regen_token') {
        $pdo->prepare('UPDATE clinics SET token = ? WHERE id = ?')
            ->execute([bin2hex(random_bytes(24)), $id]);
        log_action($pdo, 'update', 'clinic', $id, 'تجديد رمز الربط: ' . $c['name']);
        flash('تم تجديد الرمز. حدّثه في نسخة العيادة وإلا توقف اتصالها.', 'warning');
        redirect('clinic.php?id=' . $id);
    }
}

require_once __DIR__ . '/inc/provision.php';

$invoices = $pdo->prepare('SELECT * FROM invoices WHERE clinic_id = ? ORDER BY id DESC');
$invoices->execute([$id]);
$invoices = $invoices->fetchAll();

$pays = $pdo->prepare('SELECT pm.*, i.number FROM payments pm LEFT JOIN invoices i ON i.id = pm.invoice_id
                       WHERE pm.clinic_id = ? ORDER BY pm.id DESC');
$pays->execute([$id]);
$pays = $pays->fetchAll();

$totalPaid = 0.0;
foreach ($pays as $p) {
    if ($p['status'] === 'confirmed') {
        $totalPaid += (float)$p['amount'];
    }
}
$due = 0.0;
foreach ($invoices as $i) {
    if (in_array($i['status'], ['unpaid', 'partial'], true)) {
        $due += (float)$i['amount'] - (float)$i['paid'];
    }
}
$daysLeft = days_until($c['expires_at']);

page_header($c['name'], 'clinics.php');
?>
<div class="card">
    <div class="card-head">
        <h2><?= e($c['name']) ?>
            <span class="badge <?= e(CLINIC_STATUS_BADGE[$c['status']]) ?>"><?= e(CLINIC_STATUS[$c['status']]) ?></span></h2>
        <div class="actions">
            <a class="btn" href="invoices.php?new=1&clinic=<?= $id ?>">+ فاتورة</a>
            <a class="btn btn-light" href="clinics.php?edit=<?= $id ?>">تعديل</a>
        </div>
    </div>
    <div class="grid4">
        <div><span class="muted">صاحب العيادة</span><br><strong><?= e($c['owner_name'] ?: '—') ?></strong></div>
        <div><span class="muted">الهاتف</span><br><strong dir="ltr"><?= e($c['phone'] ?: '—') ?></strong></div>
        <div><span class="muted">الخطة</span><br><strong><?= e($c['plan_name'] ?? '—') ?></strong></div>
        <div><span class="muted">ينتهي في</span><br>
            <strong><?= e(fmt_date($c['expires_at'])) ?></strong>
            <?php if ($daysLeft !== null): ?>
                <?php if ($daysLeft < 0): ?><span class="badge bad">منتهٍ من <?= abs($daysLeft) ?> يوم</span>
                <?php elseif ($daysLeft <= EXPIRY_WARN_DAYS): ?><span class="badge warn"><?= $daysLeft ?> يوم</span>
                <?php else: ?><span class="badge ok"><?= $daysLeft ?> يوم</span><?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($c['site_url']): ?>
        <p style="margin-top:12px"><span class="muted">الرابط:</span>
            <a href="<?= e($c['site_url']) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($c['site_url']) ?></a></p>
    <?php endif; ?>
    <?php if ($c['notes']): ?>
        <p class="muted" style="white-space:pre-line;margin-top:8px"><?= e($c['notes']) ?></p>
    <?php endif; ?>
</div>

<div class="stats">
    <div class="stat"><div class="label">إجمالي المحصَّل</div><div class="value"><?= e(money($totalPaid)) ?></div></div>
    <div class="stat <?= $due > 0.005 ? 'bad' : '' ?>"><div class="label">مستحق عليها</div><div class="value"><?= e(money($due)) ?></div></div>
    <div class="stat"><div class="label">عدد المرضى</div><div class="value"><?= (int)$c['patients_count'] ?></div></div>
    <div class="stat"><div class="label">إصدار النظام</div><div class="value"><?= e($c['app_version'] ?: '—') ?></div></div>
</div>

<div class="card">
    <h2>🧾 الفواتير</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>الرقم</th><th>التاريخ</th><th>الاستحقاق</th><th>المبلغ</th><th>المسدد</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($invoices as $i): ?>
            <tr>
                <td dir="ltr"><?= e($i['number']) ?><br><small class="muted"><?= e($i['plan_name']) ?></small></td>
                <td class="num"><?= e(fmt_date($i['issue_date'])) ?></td>
                <td class="num"><?= e(fmt_date($i['due_date'])) ?></td>
                <td class="num"><?= e(money($i['amount'])) ?></td>
                <td class="num"><?= e(money($i['paid'])) ?></td>
                <td><span class="badge <?= e(INV_STATUS_BADGE[$i['status']]) ?>"><?= e(INV_STATUS[$i['status']]) ?></span></td>
                <td><a class="btn btn-sm" href="invoice.php?id=<?= (int)$i['id'] ?>">فتح</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$invoices): ?><tr><td colspan="7" class="muted">لا توجد فواتير.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <h2>💳 المدفوعات</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>الفاتورة</th><th>المبلغ</th><th>الوسيلة</th><th>المرجع</th><th>الحالة</th></tr></thead>
        <tbody>
        <?php foreach ($pays as $p): ?>
            <tr>
                <td class="num"><?= e(fmt_date($p['pdate'])) ?></td>
                <td dir="ltr"><?= e($p['number'] ?? '—') ?></td>
                <td class="num"><?= e(money($p['amount'])) ?></td>
                <td><?= e(PAY_METHODS[$p['method']] ?? $p['method']) ?></td>
                <td dir="ltr"><small><?= e($p['reference'] ?: '—') ?></small></td>
                <td><span class="badge <?= e(PAY_STATUS_BADGE[$p['status']]) ?>"><?= e(PAY_STATUS[$p['status']]) ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$pays): ?><tr><td colspan="6" class="muted">لا توجد مدفوعات.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php if ($c['db_name'] !== ''): $url = $c['subdomain'] ? tenant_url($c['subdomain']) : ''; ?>
<div class="card">
    <h2>🏢 عيادة SaaS</h2>
    <div class="grid3">
        <div><span class="muted">العنوان</span><br>
            <?= $url ? '<a href="' . e($url) . '" target="_blank" rel="noopener" dir="ltr">' . e($url) . '</a>'
                     : '<span class="muted">اضبط النطاق الأساسي في الإعدادات</span>' ?></div>
        <div><span class="muted">قاعدة البيانات</span><br><strong dir="ltr"><?= e($c['db_name']) ?></strong></div>
        <div><span class="muted">النطاق المخصص</span><br>
            <?= $c['custom_domain'] !== '' ? '<strong dir="ltr">' . e($c['custom_domain']) . '</strong>' : '<span class="muted">—</span>' ?></div>
    </div>
    <p class="muted" style="margin-top:10px">هذه العيادة تعمل على قاعدتها الخاصة داخل نظامك،
        فلا تحتاج رمز ربط ولا نبضة — حالة اشتراكها تُقرأ مباشرة من هنا.</p>
</div>
<?php else: ?>
<div class="card">
    <h2>🔗 ربط نسخة العيادة</h2>
    <p class="muted">ضع هذين السطرين في ملف <code>clinic/inc/config.php</code> على سيرفر العيادة،
        فترسل نسخته حالتها للكونسول يوميًا ويظهر لها تنبيه قبل انتهاء الاشتراك.</p>
    <pre dir="ltr"><?= e("define('LICENSE_URL', '" . rtrim(setting('console_url', 'https://console.example.com'), '/') . "/ping.php');\ndefine('LICENSE_TOKEN', '" . $c['token'] . "');") ?></pre>
    <form method="post" onsubmit="return confirm('تجديد الرمز سيوقف اتصال النسخة الحالية حتى تحدّثها. متابعة؟')">
        <?= csrf_field() ?><input type="hidden" name="action" value="regen_token">
        <button class="btn btn-light btn-sm" type="submit">تجديد الرمز</button>
    </form>
</div>
<?php endif; ?>
<?php page_footer();
