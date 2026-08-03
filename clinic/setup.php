<?php
/**
 * معالج تهيئة العيادة — يمشي بالمدير خطوة بخطوة بعد التثبيت.
 *
 * كل خطوة تُحفظ بمفردها، فالخروج في المنتصف لا يضيّع ما سبق. يمكن إعادة فتحه
 * في أي وقت من الإعدادات لتغيير الوحدات أو الأسعار.
 */
require __DIR__ . '/inc/bootstrap.php';
require_perm('settings.manage');

const SETUP_STEPS = [
    1 => ['بيانات العيادة', '🏥'],
    2 => ['ما تحتاجه العيادة', '🧩'],
    3 => ['فريق العمل', '👥'],
    4 => ['الأسعار والباقات', '💰'],
    5 => ['خلصنا', '🎉'],
];

$step = (int)($_GET['step'] ?? 1);
if (!isset(SETUP_STEPS[$step])) {
    $step = 1;
}

$save = $pdo->prepare('INSERT INTO settings (skey, svalue) VALUES (?, ?)
                       ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $posted = (int)($_POST['step'] ?? 1);

    if ($posted === 1) {
        $name = trim($_POST['clinic_name'] ?? '');
        if ($name === '') {
            flash('اسم العيادة مطلوب.', 'danger');
            redirect('setup.php?step=1');
        }
        $save->execute(['clinic_name', $name]);
        foreach (['clinic_phone', 'clinic_address', 'print_note'] as $k) {
            $save->execute([$k, trim($_POST[$k] ?? '')]);
        }
        $save->execute(['currency', trim($_POST['currency'] ?? '') ?: 'ج.م']);
        $save->execute(['country_code', preg_replace('/\D/', '', $_POST['country_code'] ?? '') ?: '20']);
        setting_flush();
        activity($pdo, 'setup', 'system', null, 'معالج التهيئة: بيانات العيادة');
        redirect('setup.php?step=2');
    }

    if ($posted === 2) {
        foreach (array_keys(CLINIC_MODULES) as $m) {
            $save->execute(['mod_' . $m, isset($_POST['mod'][$m]) ? '1' : '0']);
        }
        $save->execute(['portal_enabled', isset($_POST['portal_enabled']) ? '1' : '0']);
        $save->execute(['doctor_scope', ($_POST['doctor_scope'] ?? 'own') === 'all' ? 'all' : 'own']);
        $days = (int)($_POST['inactive_days'] ?? 45);
        $save->execute(['inactive_days', (string)max(7, min(365, $days))]);
        setting_flush();
        activity($pdo, 'setup', 'system', null, 'معالج التهيئة: الوحدات وطريقة العمل');
        redirect('setup.php?step=3');
    }

    if ($posted === 3) {
        // إضافة حساب واحد في كل مرة، فيبقى المدير في الخطوة حتى ينتهي من الفريق
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $role = in_array($_POST['role'] ?? '', ['doctor', 'reception'], true) ? $_POST['role'] : 'reception';

        if ($name === '' || $username === '') {
            flash('اكتب الاسم واسم الدخول.', 'danger');
        } elseif (mb_strlen($password) < 8) {
            flash('كلمة المرور يجب ألا تقل عن 8 أحرف.', 'danger');
        } else {
            $dup = $pdo->prepare('SELECT id FROM users WHERE username = ?');
            $dup->execute([$username]);
            if ($dup->fetchColumn()) {
                flash('اسم الدخول «' . $username . '» مستخدم بالفعل.', 'danger');
            } else {
                $pdo->prepare('INSERT INTO users (name, username, password, role, active) VALUES (?,?,?,?,1)')
                    ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role]);
                activity($pdo, 'create', 'user', (int)$pdo->lastInsertId(),
                    'معالج التهيئة: إضافة ' . (ROLES[$role] ?? $role) . ' — ' . $name);
                flash('تمت إضافة ' . $name . '.');
            }
        }
        redirect('setup.php?step=3');
    }

    if ($posted === 4) {
        $save->execute(['price_new', (string)max(0, (float)($_POST['price_new'] ?? 0))]);
        $save->execute(['price_followup', (string)max(0, (float)($_POST['price_followup'] ?? 0))]);

        // الباقات: تعديل الموجود، وإيقاف (لا حذف) ما تُرك اسمه فارغًا — الباقة
        // الموقوفة تختفي من قائمة البيع ويمكن إرجاعها، والباقات المُباعة تظل مرتبطة بها
        $upd = $pdo->prepare('UPDATE packages SET name=?, sessions=?, price=?, validity_days=?, active=1 WHERE id=?');
        $off = $pdo->prepare('UPDATE packages SET active=0 WHERE id=?');
        foreach ($_POST['pkg'] ?? [] as $id => $row) {
            $id = (int)$id;
            if (!$id) {
                continue;
            }
            $pname = trim($row['name'] ?? '');
            if ($pname === '') {
                $off->execute([$id]);
                continue;
            }
            $upd->execute([
                $pname,
                max(1, (int)($row['sessions'] ?? 1)),
                max(0, (float)($row['price'] ?? 0)),
                max(1, (int)($row['validity_days'] ?? 90)),
                $id,
            ]);
        }
        if (trim($_POST['new_pkg_name'] ?? '') !== '') {
            $pdo->prepare('INSERT INTO packages (name, sessions, price, validity_days) VALUES (?,?,?,?)')
                ->execute([
                    trim($_POST['new_pkg_name']),
                    max(1, (int)($_POST['new_pkg_sessions'] ?? 1)),
                    max(0, (float)($_POST['new_pkg_price'] ?? 0)),
                    max(1, (int)($_POST['new_pkg_validity'] ?? 90)),
                ]);
        }
        setting_flush();
        activity($pdo, 'setup', 'system', null, 'معالج التهيئة: الأسعار والباقات');
        redirect('setup.php?step=' . (isset($_POST['stay']) ? '4' : '5'));
    }

    if ($posted === 5) {
        $skipped = isset($_POST['skip']);
        $save->execute(['setup_done', '1']);
        setting_flush();
        activity($pdo, 'setup', 'system', null,
            $skipped ? 'تخطّي معالج التهيئة' : 'اكتمل معالج تهيئة العيادة');
        flash($skipped
            ? 'تم إخفاء المعالج. تقدر تفتحه في أي وقت من الإعدادات.'
            : 'تم ضبط العيادة. بالتوفيق 🌿');
        redirect('index.php');
    }
}

$cur = [];
foreach ($pdo->query('SELECT skey, svalue FROM settings') as $r) {
    $cur[$r['skey']] = $r['svalue'];
}
$v = fn(string $k, string $d = '') => e($cur[$k] ?? $d);

page_header('تهيئة العيادة');
?>
<div class="card setup-steps">
    <?php foreach (SETUP_STEPS as $n => [$label, $icon]): ?>
        <?php $cls = $n === $step ? 'now' : ($n < $step ? 'done' : ''); ?>
        <div class="setup-step <?= $cls ?>">
            <span class="ico"><?= $n < $step ? '✔' : $icon ?></span>
            <span><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($step === 1): ?>
<div class="card">
    <h2>🏥 بيانات العيادة</h2>
    <p class="muted">هذه البيانات تظهر في رأس النظام وعلى الأنظمة الغذائية والإيصالات المطبوعة.</p>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="step" value="1">
        <label>اسم العيادة *
            <input name="clinic_name" value="<?= $v('clinic_name') ?>" required
                   placeholder="مثال: عيادة د. أحمد للتغذية العلاجية"></label>
        <div class="grid2">
            <label>هاتف العيادة <input name="clinic_phone" value="<?= $v('clinic_phone') ?>" dir="ltr" placeholder="01000000000"></label>
            <label>العنوان <input name="clinic_address" value="<?= $v('clinic_address') ?>" placeholder="المدينة — الشارع — رقم العيادة"></label>
            <label>العملة <input name="currency" value="<?= $v('currency', 'ج.م') ?>" placeholder="ج.م"></label>
            <label>كود الدولة لواتساب
                <input name="country_code" value="<?= $v('country_code', '20') ?>" dir="ltr">
                <small class="muted">مصر 20 · السعودية 966 · الإمارات 971 · الكويت 965</small></label>
        </div>
        <label>عبارة أسفل الأوراق المطبوعة
            <input name="print_note" value="<?= $v('print_note', 'نتمنى لكم دوام الصحة والعافية 🌿') ?>"></label>
        <div class="actions"><button class="btn" type="submit">التالي ←</button></div>
    </form>
</div>

<?php elseif ($step === 2): ?>
<div class="card">
    <h2>🧩 ما الذي تحتاجه عيادتك؟</h2>
    <p class="muted">شغّل ما تستخدمه فقط — ما تُطفئه يختفي من القائمة، ويمكنك تشغيله لاحقًا في أي وقت.</p>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="step" value="2">
        <div class="setup-mods">
            <?php foreach (CLINIC_MODULES as $key => [$label, $icon, $desc]): ?>
                <label class="setup-mod">
                    <input type="checkbox" name="mod[<?= e($key) ?>]" value="1"
                        <?= ($cur['mod_' . $key] ?? '1') === '1' ? 'checked' : '' ?>>
                    <span>
                        <strong><?= $icon ?> <?= e($label) ?></strong>
                        <small class="muted"><?= e($desc) ?></small>
                    </span>
                </label>
            <?php endforeach; ?>
            <label class="setup-mod">
                <input type="checkbox" name="portal_enabled" value="1" <?= ($cur['portal_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                <span>
                    <strong>📱 بوابة المريض</strong>
                    <small class="muted">تطبيق للموبايل يتابع فيه المريض وزنه ونظامه وحسابه</small>
                </span>
            </label>
        </div>

        <h3 class="form-section">لو في أكثر من طبيب</h3>
        <div class="grid2">
            <label>كل طبيب يرى
                <select name="doctor_scope">
                    <option value="own" <?= ($cur['doctor_scope'] ?? 'own') === 'own' ? 'selected' : '' ?>>مرضاه فقط (الأنسب للعيادات المشتركة)</option>
                    <option value="all" <?= ($cur['doctor_scope'] ?? '') === 'all' ? 'selected' : '' ?>>كل مرضى العيادة</option>
                </select></label>
            <label>يُعتبر المريض متوقفًا عن المتابعة بعد
                <input type="number" name="inactive_days" min="7" max="365" value="<?= $v('inactive_days', '45') ?>">
                <small class="muted">يومًا بدون زيارة — يظهر في تقرير المتوقفين للتواصل معه</small></label>
        </div>
        <div class="actions">
            <button class="btn" type="submit">التالي ←</button>
            <a class="btn btn-light" href="setup.php?step=1">→ السابق</a>
        </div>
    </form>
</div>

<?php elseif ($step === 3):
    $team = $pdo->query('SELECT id, name, username, role FROM users ORDER BY id')->fetchAll(); ?>
<div class="card">
    <h2>👥 فريق العمل</h2>
    <p class="muted">أضف الأطباء وموظفي الاستقبال. الصلاحيات تُضبط تلقائيًا حسب الدور، ويمكن تعديلها
        بالتفصيل لكل حساب لاحقًا من صفحة المستخدمين.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>الدور</th></tr></thead>
        <tbody>
            <?php foreach ($team as $t): ?>
                <tr>
                    <td><?= e($t['name']) ?></td>
                    <td dir="ltr"><?= e($t['username']) ?></td>
                    <td><span class="badge"><?= e(ROLES[$t['role']] ?? $t['role']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<div class="card">
    <h3>إضافة حساب</h3>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="step" value="3">
        <div class="grid2">
            <label>الاسم <input name="name" required placeholder="مثال: د. سارة محمود"></label>
            <label>اسم الدخول <input name="username" required dir="ltr" placeholder="dr.sara"></label>
            <label>كلمة المرور (8 أحرف على الأقل) <input type="password" name="password" required minlength="8" dir="ltr"></label>
            <label>الدور
                <select name="role">
                    <option value="doctor">طبيب / أخصائي تغذية</option>
                    <option value="reception">استقبال</option>
                </select></label>
        </div>
        <div class="actions"><button class="btn btn-light" type="submit">+ إضافة الحساب</button></div>
    </form>
</div>
<div class="card">
    <div class="actions">
        <a class="btn" href="setup.php?step=4">التالي ←</a>
        <a class="btn btn-light" href="setup.php?step=2">→ السابق</a>
    </div>
    <p class="muted">تقدر تتخطى دي وتضيف الحسابات بعدين من صفحة المستخدمين.</p>
</div>

<?php elseif ($step === 4):
    $pkgs = $pdo->query('SELECT id, name, sessions, price, validity_days FROM packages WHERE active = 1 ORDER BY sessions')->fetchAll(); ?>
<div class="card">
    <h2>💰 الأسعار والباقات</h2>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="step" value="4">
        <div class="grid2">
            <label>سعر الكشف الجديد <input type="number" step="0.01" min="0" name="price_new" value="<?= $v('price_new', '300') ?>"></label>
            <label>سعر المتابعة <input type="number" step="0.01" min="0" name="price_followup" value="<?= $v('price_followup', '150') ?>"></label>
        </div>

        <?php if (module_on('packages')): ?>
            <h3 class="form-section">باقات الجلسات</h3>
            <p class="muted">عدّل الباقات المقترحة بما يناسب أسعارك. امسح اسم الباقة لإيقافها
                (تختفي من قائمة البيع ولا تُحذف، وتقدر ترجّعها من صفحة الباقات).</p>
            <div class="table-wrap"><table>
                <thead><tr><th>اسم الباقة</th><th>عدد الجلسات</th><th>السعر</th><th>صالحة (يوم)</th></tr></thead>
                <tbody>
                    <?php foreach ($pkgs as $p): ?>
                        <tr>
                            <td><input name="pkg[<?= (int)$p['id'] ?>][name]" value="<?= e($p['name']) ?>"></td>
                            <td><input type="number" min="1" name="pkg[<?= (int)$p['id'] ?>][sessions]" value="<?= (int)$p['sessions'] ?>"></td>
                            <td><input type="number" step="0.01" min="0" name="pkg[<?= (int)$p['id'] ?>][price]" value="<?= e($p['price']) ?>"></td>
                            <td><input type="number" min="1" name="pkg[<?= (int)$p['id'] ?>][validity_days]" value="<?= (int)$p['validity_days'] ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <td><input name="new_pkg_name" placeholder="+ باقة جديدة"></td>
                        <td><input type="number" min="1" name="new_pkg_sessions" placeholder="4"></td>
                        <td><input type="number" step="0.01" min="0" name="new_pkg_price" placeholder="500"></td>
                        <td><input type="number" min="1" name="new_pkg_validity" placeholder="60"></td>
                    </tr>
                </tbody>
            </table></div>
            <div class="actions">
                <button class="btn" type="submit">التالي ←</button>
                <button class="btn btn-light" type="submit" name="stay" value="1">حفظ وإضافة باقة أخرى</button>
                <a class="btn btn-light" href="setup.php?step=3">→ السابق</a>
            </div>
        <?php else: ?>
            <div class="actions">
                <button class="btn" type="submit">التالي ←</button>
                <a class="btn btn-light" href="setup.php?step=3">→ السابق</a>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php else:
    $counts = [
        'مستخدمون' => (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'برامج تغذية جاهزة' => (int)$pdo->query('SELECT COUNT(*) FROM diet_templates')->fetchColumn(),
        'باقات مفعّلة' => (int)$pdo->query('SELECT COUNT(*) FROM packages WHERE active = 1')->fetchColumn(),
    ];
    $on = array_filter(array_keys(CLINIC_MODULES), 'module_on');
    $installFile = is_file(__DIR__ . '/install.php'); ?>
<div class="card">
    <h2>🎉 العيادة جاهزة</h2>
    <p><strong><?= $v('clinic_name') ?></strong> مضبوطة وجاهزة لاستقبال أول مريض.</p>
    <div class="stats">
        <?php foreach ($counts as $label => $n): ?>
            <div class="stat"><div class="label"><?= e($label) ?></div><div class="value"><?= $n ?></div></div>
        <?php endforeach; ?>
        <div class="stat"><div class="label">وحدات مفعّلة</div><div class="value"><?= count($on) ?></div></div>
    </div>
</div>

<?php if ($installFile): ?>
<div class="card">
    <div class="alert alert-danger" style="margin:0">
        <strong>مهم للأمان:</strong> ملف <code>install.php</code> لسه موجود على السيرفر — احذفه دلوقتي
        من مدير الملفات في cPanel. طالما موجود يقدر أي حد يفتحه.
    </div>
</div>
<?php endif; ?>

<div class="card">
    <h3>خطوات أخيرة مفيدة</h3>
    <ul class="checklist">
        <li><strong>شغّل التذكير التلقائي:</strong> من <a href="settings.php">الإعدادات</a> تقدر تفعّل إرسال
            تذكيرات المواعيد يوميًا بالكرون، أو تكتفي بإرسال الواتساب بضغطة من صفحة تأكيد المواعيد.</li>
        <li><strong>خُد نسخة احتياطية:</strong> من <a href="backup.php">صفحة النسخ الاحتياطي</a> — يُفضّل مرة أسبوعيًا.</li>
        <li><strong>اضبط الصلاحيات بالتفصيل:</strong> لو محتاج موظف الاستقبال يسجّل مدفوعات بدون حذفها مثلًا،
            من <a href="users.php">المستخدمين</a>.</li>
        <li><strong>ابدأ بأول مريض:</strong> <a href="patients.php?new=1">إضافة مريض جديد</a>.</li>
    </ul>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="step" value="5">
        <div class="actions">
            <button class="btn" type="submit">تم — ابدأ استخدام النظام</button>
            <a class="btn btn-light" href="setup.php?step=1">مراجعة الخطوات من الأول</a>
        </div>
    </form>
</div>
<?php endif; ?>
<?php page_footer();
