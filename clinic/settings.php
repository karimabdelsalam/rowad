<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('settings.manage');

$keys = [
    'clinic_name'    => 'اسم العيادة',
    'clinic_phone'   => 'هاتف العيادة',
    'clinic_address' => 'عنوان العيادة',
    'currency'       => 'العملة',
    'price_new'      => 'سعر الكشف الجديد',
    'price_followup' => 'سعر المتابعة',
    'print_note'     => 'عبارة أسفل النظام الغذائي المطبوع',
    'country_code'   => 'كود الدولة لأرقام واتساب',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $st = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach (array_keys($keys) as $k) {
        $st->execute([$k, trim($_POST[$k] ?? '')]);
    }
    $template = trim($_POST['wa_template'] ?? '');
    $st->execute(['wa_template', $template !== '' ? $template : wa_default_template()]);

    foreach (['doctor_scope', 'notify_channel', 'notify_provider', 'notify_url',
              'notify_sender', 'notify_lead_days'] as $k) {
        $st->execute([$k, trim($_POST[$k] ?? '')]);
    }
    $st->execute(['notify_enabled', isset($_POST['notify_enabled']) ? '1' : '0']);
    $st->execute(['portal_enabled', isset($_POST['portal_enabled']) ? '1' : '0']);

    // لا تمسح الرمز المحفوظ لو تُرك الحقل فارغًا
    if (trim($_POST['notify_token'] ?? '') !== '') {
        $st->execute(['notify_token', trim($_POST['notify_token'])]);
    }
    if (isset($_POST['regen_cron'])) {
        $st->execute(['cron_token', bin2hex(random_bytes(16))]);
    }

    setting_flush();
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
        <h3 class="form-section">💬 رسالة تذكير واتساب</h3>
        <label>نص الرسالة المُرسلة للمرضى قبل الموعد
            <textarea name="wa_template" rows="6"><?= e($current['wa_template'] ?? wa_default_template()) ?></textarea>
        </label>
        <p class="muted" style="margin-bottom:10px">الكلمات بين الأقواس تُستبدل تلقائيًا ببيانات كل مريض:</p>
        <div class="table-wrap" style="margin-bottom:14px"><table>
            <thead><tr><th>الكلمة</th><th>تُستبدل بـ</th></tr></thead>
            <tbody>
            <?php foreach (WA_PLACEHOLDERS as $ph => $desc): ?>
                <tr><td><code><?= e($ph) ?></code></td><td class="muted"><?= e($desc) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <h3 class="form-section">👨‍⚕️ الأطباء</h3>
        <label>ما الذي يراه الأخصائي؟
            <select name="doctor_scope">
                <option value="own" <?= ($current['doctor_scope'] ?? 'own') === 'own' ? 'selected' : '' ?>>
                    مرضاه فقط (كل طبيب يرى المرضى المسنَدين إليه)</option>
                <option value="all" <?= ($current['doctor_scope'] ?? '') === 'all' ? 'selected' : '' ?>>
                    كل مرضى العيادة</option>
            </select>
        </label>
        <p class="muted" style="margin-bottom:10px">
            المدير والاستقبال يريان كل المرضى دائمًا. أسنِد الطبيب المعالج من صفحة تعديل بيانات المريض.
        </p>

        <h3 class="form-section">⏰ التذكير التلقائي (الكرون)</h3>
        <div class="grid4">
            <label>المزوّد
                <select name="notify_provider">
                    <option value="webhook" <?= ($current['notify_provider'] ?? 'webhook') === 'webhook' ? 'selected' : '' ?>>رابط Webhook (أي بوابة)</option>
                    <option value="meta" <?= ($current['notify_provider'] ?? '') === 'meta' ? 'selected' : '' ?>>واتساب الرسمي (Meta Cloud API)</option>
                    <option value="email" <?= ($current['notify_provider'] ?? '') === 'email' ? 'selected' : '' ?>>بريد إلكتروني</option>
                    <option value="manual" <?= ($current['notify_provider'] ?? '') === 'manual' ? 'selected' : '' ?>>يدوي (تسجيل فقط)</option>
                </select>
            </label>
            <label>القناة
                <select name="notify_channel">
                    <?php foreach (MSG_CHANNELS as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= ($current['notify_channel'] ?? 'whatsapp') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>الإرسال قبل الموعد بـ (أيام)
                <input type="number" min="0" max="7" name="notify_lead_days" value="<?= e($current['notify_lead_days'] ?? '1') ?>"></label>
            <label>المُرسِل / Phone Number ID
                <input name="notify_sender" value="<?= e($current['notify_sender'] ?? '') ?>" dir="ltr"></label>
        </div>
        <div class="grid2">
            <label>رابط الإرسال (للـ Webhook)
                <input name="notify_url" value="<?= e($current['notify_url'] ?? '') ?>" dir="ltr" placeholder="https://..."></label>
            <label>رمز الوصول / Token
                <input name="notify_token" dir="ltr"
                    placeholder="<?= ($current['notify_token'] ?? '') !== '' ? 'محفوظ — اتركه فارغًا للإبقاء عليه' : 'اختياري' ?>"></label>
        </div>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="notify_enabled" style="width:auto" <?= ($current['notify_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
            تفعيل الإرسال التلقائي
        </label>

        <div class="alert alert-warning" style="font-weight:400">
            <strong>سطر الكرون في cPanel</strong> (شغّله مرة يوميًا، مثلًا 6 مساءً):
            <pre dir="ltr" style="background:#0f172a;color:#e2e8f0;padding:10px;border-radius:8px;overflow:auto;margin:8px 0;font-size:12.5px">/usr/local/bin/php <?= e(__DIR__) ?>/cron/reminders.php</pre>
            أو عبر رابط لو استضافتك بتستخدم curl:
            <pre dir="ltr" style="background:#0f172a;color:#e2e8f0;padding:10px;border-radius:8px;overflow:auto;margin:8px 0;font-size:12.5px">curl -s "<?= e(setting('site_url', 'https://موقعك/clinic')) ?>/cron/reminders.php?token=<?= e(setting('cron_token')) ?>"</pre>
            <label style="display:flex;align-items:center;gap:8px;font-weight:400">
                <input type="checkbox" name="regen_cron" style="width:auto"> توليد رمز كرون جديد (يُبطل الرابط القديم)
            </label>
        </div>

        <h3 class="form-section">📱 بوابة المرضى</h3>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="portal_enabled" style="width:auto" <?= ($current['portal_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
            تفعيل بوابة المرضى (يفعّلها الاستقبال لكل مريض على حدة من ملفه)
        </label>

        <button class="btn" type="submit">حفظ الإعدادات</button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h2>📨 سجل الرسائل المُرسلة</h2>
        <a class="btn btn-light btn-sm" href="settings.php?log=1">تحديث</a>
    </div>
    <?php
    $msgs = $pdo->query(
        'SELECT m.*, p.name AS pname FROM message_log m LEFT JOIN patients p ON p.id = m.patient_id
         ORDER BY m.id DESC LIMIT 25'
    )->fetchAll();
    ?>
    <?php if (!$msgs): ?>
        <p class="muted">لم تُرسل رسائل تلقائية بعد.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr><th>التاريخ</th><th>المريض</th><th>القناة</th><th>الحالة</th><th>السبب / الخطأ</th></tr></thead>
        <tbody>
        <?php foreach ($msgs as $m): ?>
            <tr>
                <td class="num"><?= e(date('d/m H:i', strtotime($m['created_at']))) ?></td>
                <td><?= e($m['pname'] ?? '—') ?></td>
                <td><?= e(MSG_CHANNELS[$m['channel']] ?? $m['channel']) ?></td>
                <td><span class="badge <?= $m['status'] === 'sent' ? 'ok' : ($m['status'] === 'failed' ? 'bad' : 'muted') ?>">
                    <?= $m['status'] === 'sent' ? 'أُرسلت' : ($m['status'] === 'failed' ? 'فشلت' : 'تخطّي') ?></span></td>
                <td class="muted"><?= e($m['error']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>معلومات النظام</h2>
    <p class="muted">نظام إدارة عيادة التغذية والتخسيس — إصدار 1.0</p>
    <p class="muted">PHP: <?= e(PHP_VERSION) ?></p>
    <p class="muted">تذكير: احرص على أخذ نسخة احتياطية من قاعدة البيانات بشكل دوري من cPanel → phpMyAdmin → تصدير.</p>
</div>
<?php page_footer();
