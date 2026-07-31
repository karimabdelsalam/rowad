<?php
/** إعدادات الكونسول ووسائل الدفع. */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/paymob.php';
require_login();

$plain = [
    'brand_name'    => 'اسم شركتك أو نشاطك',
    'currency'      => 'العملة',
    'console_url'   => 'رابط الكونسول (بدون / في آخره)',
    'support_phone' => 'هاتف الدعم',
    'grace_days'    => 'مهلة السماح بعد انتهاء الاشتراك (أيام)',
];
$saas = ['base_domain', 'base_scheme', 'tenant_prefix', 'trial_days'];
$instapay = [
    'instapay_addr' => 'عنوان إنستا باي (‎@اسمك أو رقم الموبايل)',
    'instapay_note' => 'تعليمات تظهر للعميل',
];
$paymobKeys = [
    'paymob_api_key'        => 'API Key',
    'paymob_integration_id' => 'Integration ID',
    'paymob_iframe_id'      => 'iFrame ID',
    'paymob_hmac'           => 'HMAC Secret',
    'paymob_currency'       => 'العملة (EGP)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach ([...array_keys($plain), ...array_keys($instapay), ...$saas] as $k) {
        $st->execute([$k, trim($_POST[$k] ?? '')]);
    }
    $st->execute(['signup_open', isset($_POST['signup_open']) ? '1' : '0']);
    foreach (array_keys($paymobKeys) as $k) {
        // المفاتيح السرية لا تُمسح لو تُرك الحقل فارغًا
        $v = trim($_POST[$k] ?? '');
        if ($v !== '' || !in_array($k, ['paymob_api_key', 'paymob_hmac'], true)) {
            $st->execute([$k, $v]);
        }
    }
    log_action($pdo, 'update', 'settings', null, 'تحديث إعدادات الكونسول');
    flash('تم حفظ الإعدادات.');
    redirect('settings.php');
}

$cur = [];
foreach ($pdo->query('SELECT skey, svalue FROM settings') as $r) {
    $cur[$r['skey']] = $r['svalue'];
}
$v = fn(string $k, string $d = '') => e($cur[$k] ?? $d);
$base = rtrim($cur['console_url'] ?? '', '/');

page_header('الإعدادات', 'settings.php');
?>
<div class="card">
    <h2>⚙️ إعدادات عامة</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="grid2">
            <?php foreach ($plain as $k => $label): ?>
                <label><?= e($label) ?> <input name="<?= e($k) ?>" value="<?= $v($k) ?>"
                    <?= in_array($k, ['console_url', 'support_phone'], true) ? 'dir="ltr"' : '' ?>
                    <?= $k === 'console_url' ? 'placeholder="https://console.example.com"' : '' ?>></label>
            <?php endforeach; ?>
        </div>

        <h3 class="form-section">🏢 وضع SaaS</h3>
        <p class="muted">لكل عيادة نطاق فرعي وقاعدة بيانات مستقلة تحت النطاق الأساسي.
            اضبط في DNS سجل <code dir="ltr">*.نطاقك</code> يشير لهذا السيرفر.</p>
        <div class="grid3">
            <label>النطاق الأساسي
                <input name="base_domain" value="<?= $v('base_domain') ?>" dir="ltr" placeholder="myclinic.app"></label>
            <label>البروتوكول
                <select name="base_scheme">
                    <option value="https" <?= ($cur['base_scheme'] ?? 'https') === 'https' ? 'selected' : '' ?>>https</option>
                    <option value="http" <?= ($cur['base_scheme'] ?? '') === 'http' ? 'selected' : '' ?>>http</option>
                </select></label>
            <label>بادئة أسماء القواعد
                <input name="tenant_prefix" value="<?= $v('tenant_prefix', 'clinic_') ?>" dir="ltr">
                <small class="muted">يجب أن تطابق منحة MySQL</small></label>
        </div>
        <div class="grid2">
            <label>أيام التجربة المجانية
                <input type="number" min="0" max="90" name="trial_days" value="<?= $v('trial_days', '14') ?>"></label>
            <label style="display:flex;align-items:center;gap:8px;margin-top:26px">
                <input type="checkbox" name="signup_open" style="width:auto" <?= ($cur['signup_open'] ?? '0') === '1' ? 'checked' : '' ?>>
                فتح التسجيل الذاتي للعامة (<code dir="ltr">signup.php</code>)
            </label>
        </div>

        <h3 class="form-section">📲 إنستا باي</h3>
        <p class="muted">تحويل يدوي: يظهر العنوان للعميل في صفحة السداد، ثم تؤكّد وصول المبلغ من صفحة المدفوعات.</p>
        <label><?= e($instapay['instapay_addr']) ?>
            <input name="instapay_addr" value="<?= $v('instapay_addr') ?>" dir="ltr" placeholder="yourname@instapay"></label>
        <label><?= e($instapay['instapay_note']) ?>
            <input name="instapay_note" value="<?= $v('instapay_note') ?>"></label>

        <h3 class="form-section">💳 باي موب</h3>
        <?php if (paymob_configured()): ?>
            <div class="alert alert-success">البوابة مضبوطة ✔ — أجرِ عملية تجريبية بمبلغ صغير قبل استخدامها مع العملاء.</div>
        <?php else: ?>
            <div class="alert alert-warning">البوابة غير مكتملة، فلا يظهر خيار الدفع الأونلاين للعملاء.</div>
        <?php endif; ?>
        <p class="muted">المفاتيح من لوحة باي موب: <span dir="ltr">Settings → Account Info</span>.
            اترك <strong>API Key</strong> و<strong>HMAC</strong> فارغين للإبقاء على المحفوظ.</p>
        <div class="grid2">
            <?php foreach ($paymobKeys as $k => $label): ?>
                <?php $secret = in_array($k, ['paymob_api_key', 'paymob_hmac'], true); ?>
                <label><?= e($label) ?>
                    <input name="<?= e($k) ?>" dir="ltr"
                        <?= $secret ? 'type="password" placeholder="' . (($cur[$k] ?? '') !== '' ? '•••• محفوظ' : 'غير مضبوط') . '"'
                                    : 'value="' . $v($k, $k === 'paymob_currency' ? 'EGP' : '') . '"' ?>></label>
            <?php endforeach; ?>
        </div>

        <button class="btn" type="submit">حفظ الإعدادات</button>
    </form>
</div>

<div class="card">
    <h2>🔌 روابط تُضبط في لوحة باي موب</h2>
    <?php if ($base === ''): ?>
        <div class="alert alert-warning">اكتب رابط الكونسول أعلاه أولًا لتظهر الروابط جاهزة للنسخ.</div>
    <?php else: ?>
    <p class="muted"><span dir="ltr">Developers → Payment Integrations → </span>عدّل قناة التحصيل وضع:</p>
    <label>Transaction Processed Callback
        <input value="<?= e($base . '/paymob_callback.php') ?>" dir="ltr" readonly onclick="this.select()"></label>
    <label>Transaction Response Callback
        <input value="<?= e($base . '/paymob_return.php') ?>" dir="ltr" readonly onclick="this.select()"></label>
    <?php endif; ?>
</div>
<?php page_footer();
