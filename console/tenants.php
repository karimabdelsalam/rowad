<?php
/** إنشاء عيادات SaaS وتشغيل الترقيات على كل قواعدها. */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/provision.php';
require_login();

$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $in = [
            'clinic_name' => trim($_POST['clinic_name'] ?? ''),
            'owner_name'  => trim($_POST['owner_name'] ?? ''),
            'phone'       => trim($_POST['phone'] ?? ''),
            'email'       => trim($_POST['email'] ?? ''),
            'subdomain'   => strtolower(trim($_POST['subdomain'] ?? '')),
            'admin_name'  => trim($_POST['admin_name'] ?? ''),
            'admin_user'  => trim($_POST['admin_user'] ?? ''),
            'admin_pass'  => (string)($_POST['admin_pass'] ?? ''),
            'plan_id'     => (int)($_POST['plan_id'] ?? 0),
        ];
        $errs = [];
        if ($in['clinic_name'] === '') $errs[] = 'اسم العيادة مطلوب.';
        if ($in['admin_name'] === '' || $in['admin_user'] === '') $errs[] = 'بيانات حساب المدير مطلوبة.';
        if (mb_strlen($in['admin_pass']) < 8) $errs[] = 'كلمة المرور 8 أحرف على الأقل.';
        $errs = array_merge($errs, subdomain_errors($pdo, $in['subdomain']));

        if ($errs) {
            flash(implode(' ', $errs), 'danger');
            redirect('tenants.php?new=1');
        }
        try {
            $res = create_tenant($pdo, $in);
            log_action($pdo, 'provision', 'clinic', $res['clinic_id'],
                'إنشاء عيادة SaaS: ' . $in['clinic_name'] . ' (' . $res['db_name'] . ')');
            flash('تم إنشاء العيادة وقاعدتها بنجاح.');
            redirect('clinic.php?id=' . $res['clinic_id']);
        } catch (Throwable $ex) {
            flash('فشل الإنشاء: ' . $ex->getMessage(), 'danger');
            redirect('tenants.php?new=1');
        }
    }

    if ($action === 'migrate_all') {
        $results = migrate_all_tenants($pdo);
        $failed = count(array_filter($results, fn($r) => !$r['ok']));
        log_action($pdo, 'migrate', 'system', null,
            'ترقية ' . count($results) . ' قاعدة، فشل ' . $failed);
        flash($failed
            ? "اكتملت الترقية مع فشل $failed قاعدة — راجع التفاصيل."
            : 'تمت ترقية كل قواعد العيادات.', $failed ? 'warning' : 'success');
    }

    if ($action === 'provision_db') {
        // عيادة مسجّلة بلا قاعدة (أُنشئت قبل تفعيل وضع SaaS)
        $cid = (int)($_POST['clinic_id'] ?? 0);
        $st = $pdo->prepare('SELECT * FROM clinics WHERE id = ?');
        $st->execute([$cid]);
        $c = $st->fetch();
        if (!$c || $c['db_name'] !== '') {
            flash('العيادة غير موجودة أو لها قاعدة بالفعل.', 'danger');
            redirect('tenants.php');
        }
        try {
            $db = provision_tenant_db($pdo, $cid, [
                'clinic_name' => $c['name'],
                'admin_name'  => $c['owner_name'] ?: $c['name'],
                'admin_user'  => trim($_POST['admin_user'] ?? '') ?: 'admin',
                'admin_pass'  => (string)($_POST['admin_pass'] ?? ''),
            ]);
            log_action($pdo, 'provision', 'clinic', $cid, 'إنشاء قاعدة لعيادة قائمة: ' . $db);
            flash('تم إنشاء قاعدة العيادة.');
        } catch (Throwable $ex) {
            flash('فشل الإنشاء: ' . $ex->getMessage(), 'danger');
        }
        redirect('clinic.php?id=' . $cid);
    }
}

$base = setting('base_domain', '');
$tenants = $pdo->query("SELECT c.*, p.name AS plan_name FROM clinics c
                        LEFT JOIN plans p ON p.id = c.plan_id
                        WHERE c.db_name <> '' ORDER BY c.id DESC")->fetchAll();
$orphans = $pdo->query("SELECT id, name FROM clinics WHERE db_name = '' ORDER BY name")->fetchAll();
$plans = $pdo->query('SELECT id, name, months, price FROM plans WHERE active = 1 ORDER BY months')->fetchAll();

page_header('عيادات SaaS', 'tenants.php');
?>
<?php if ($base === ''): ?>
<div class="alert alert-warning">
    اضبط <strong>النطاق الأساسي</strong> في <a href="settings.php">الإعدادات</a> أولًا
    (مثل <code dir="ltr">myclinic.app</code>) — بدونه لا تُبنى روابط العيادات.
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>🏢 عيادات SaaS (<?= count($tenants) ?>)</h2>
        <div class="actions">
            <a class="btn" href="tenants.php?new=1">+ عيادة جديدة</a>
            <form method="post" onsubmit="return confirm('تشغيل الترقية على كل قواعد العيادات؟')">
                <?= csrf_field() ?><input type="hidden" name="action" value="migrate_all">
                <button class="btn btn-light" type="submit">⬆ ترقية كل القواعد</button>
            </form>
        </div>
    </div>
    <p class="muted">كل عيادة على قاعدة بيانات مستقلة. بعد كل تحديث للنظام شغّل
        <strong>ترقية كل القواعد</strong> حتى لا تتأخر عيادة عن السكيما الجديدة.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>العيادة</th><th>العنوان</th><th>القاعدة</th><th>الخطة</th><th>الحالة</th><th>ينتهي</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tenants as $t): $d = days_until($t['expires_at']); ?>
            <tr>
                <td><a href="clinic.php?id=<?= (int)$t['id'] ?>"><strong><?= e($t['name']) ?></strong></a></td>
                <td dir="ltr"><?php $u = $t['subdomain'] ? tenant_url($t['subdomain']) : ''; ?>
                    <?= $u ? '<a href="' . e($u) . '" target="_blank" rel="noopener">' . e($t['subdomain'] . '.' . $base) . '</a>'
                           : '<span class="muted">—</span>' ?></td>
                <td dir="ltr"><small class="muted"><?= e($t['db_name']) ?></small></td>
                <td><?= e($t['plan_name'] ?? '—') ?></td>
                <td><span class="badge <?= e(CLINIC_STATUS_BADGE[$t['status']]) ?>"><?= e(CLINIC_STATUS[$t['status']]) ?></span></td>
                <td class="num"><?= e(fmt_date($t['expires_at'])) ?>
                    <?php if ($d !== null && $d < 0): ?><br><span class="badge bad">منتهٍ</span><?php endif; ?></td>
                <td><a class="btn btn-sm" href="clinic.php?id=<?= (int)$t['id'] ?>">فتح</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$tenants): ?><tr><td colspan="7" class="muted">لا توجد عيادات SaaS بعد.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php if ($results): ?>
<div class="card">
    <h2>نتيجة الترقية</h2>
    <div class="table-wrap"><table>
        <thead><tr><th>العيادة</th><th>القاعدة</th><th>النتيجة</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
            <tr>
                <td><?= e($r['clinic']) ?></td>
                <td dir="ltr"><small><?= e($r['db']) ?></small></td>
                <td><span class="badge <?= $r['ok'] ? 'ok' : 'bad' ?>"><?= $r['ok'] ? '✔' : '✖' ?></span>
                    <?= e($r['note']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php endif; ?>

<?php if ($orphans): ?>
<div class="card">
    <h2>⚠ عيادات بلا قاعدة</h2>
    <p class="muted">مسجّلة في الكونسول لكن ليس لها قاعدة SaaS — إما نسخ مستقلة عند العميل،
        أو أُضيفت يدويًا. النسخ المستقلة اتركها كما هي.</p>
    <ul>
        <?php foreach ($orphans as $o): ?>
            <li><a href="clinic.php?id=<?= (int)$o['id'] ?>"><?= e($o['name']) ?></a></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if (isset($_GET['new'])): ?>
<div class="card">
    <h2>عيادة SaaS جديدة</h2>
    <p class="muted">يُنشأ للعيادة نطاق فرعي وقاعدة بيانات مستقلة وحساب مدير، وتبدأ فترة
        تجريبية <?= (int)setting('trial_days', '14') ?> يومًا.</p>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="grid2">
            <label>اسم العيادة * <input name="clinic_name" required placeholder="عيادة د. منى للتغذية"></label>
            <label>العنوان (النطاق الفرعي) *
                <input name="subdomain" required dir="ltr" pattern="[a-z0-9][a-z0-9-]{1,28}[a-z0-9]"
                       placeholder="mona-clinic">
                <small class="muted">سيصبح: <code dir="ltr"><?= e($base !== '' ? 'الاسم.' . $base : 'الاسم.نطاقك') ?></code></small></label>
            <label>صاحب العيادة <input name="owner_name" placeholder="د. منى عبد الله"></label>
            <label>الهاتف <input name="phone" dir="ltr"></label>
            <label>البريد <input type="email" name="email" dir="ltr"></label>
            <label>الخطة
                <select name="plan_id">
                    <option value="">— بعد التجربة —</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int)$p['id'] ?>"><?= e($p['name']) ?> — <?= e(money($p['price'])) ?></option>
                    <?php endforeach; ?>
                </select></label>
        </div>
        <h3 class="form-section">حساب مدير العيادة</h3>
        <div class="grid3">
            <label>الاسم * <input name="admin_name" required></label>
            <label>اسم الدخول * <input name="admin_user" required dir="ltr" value="admin"></label>
            <label>كلمة المرور * <input type="password" name="admin_pass" required minlength="8" dir="ltr"></label>
        </div>
        <div class="actions">
            <button class="btn" type="submit">إنشاء العيادة</button>
            <a class="btn btn-light" href="tenants.php">إلغاء</a>
        </div>
    </form>
</div>
<?php endif; ?>
<?php page_footer();
