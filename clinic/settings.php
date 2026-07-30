<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');

$keys = [
    'clinic_name'    => 'اسم العيادة',
    'clinic_phone'   => 'هاتف العيادة',
    'clinic_address' => 'عنوان العيادة',
    'currency'       => 'العملة',
    'price_new'      => 'سعر الكشف الجديد',
    'price_followup' => 'سعر المتابعة',
    'print_note'     => 'عبارة أسفل النظام الغذائي المطبوع',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach (array_keys($keys) as $k) {
        $st->execute([$k, trim($_POST[$k] ?? '')]);
    }
    flash('تم حفظ الإعدادات.');
    redirect('settings.php');
}

$current = [];
foreach ($pdo->query('SELECT skey, svalue FROM settings') as $r) {
    $current[$r['skey']] = $r['svalue'];
}

page_header('الإعدادات', 'settings.php');
?>
<div class="card">
    <h2>⚙️ إعدادات العيادة</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="grid2">
            <?php foreach ($keys as $k => $label): ?>
                <label><?= e($label) ?>
                    <input name="<?= e($k) ?>" value="<?= e($current[$k] ?? '') ?>"
                        <?= in_array($k, ['price_new', 'price_followup'], true) ? 'type="number" step="0.01" min="0"' : '' ?>>
                </label>
            <?php endforeach; ?>
        </div>
        <button class="btn" type="submit">حفظ الإعدادات</button>
    </form>
</div>

<div class="card">
    <h2>معلومات النظام</h2>
    <p class="muted">نظام إدارة عيادة التغذية والتخسيس — إصدار 1.0</p>
    <p class="muted">PHP: <?= e(PHP_VERSION) ?></p>
    <p class="muted">تذكير: احرص على أخذ نسخة احتياطية من قاعدة البيانات بشكل دوري من cPanel → phpMyAdmin → تصدير.</p>
</div>
<?php page_footer();
