<?php
/** سجل العيادات العميلة. */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/provision.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('اسم العيادة مطلوب.', 'danger');
            redirect('clinics.php?new=1');
        }
        $status = array_key_exists($_POST['status'] ?? '', CLINIC_STATUS) ? $_POST['status'] : 'trial';

        // النطاق الفرعي يُغيَّر فقط لعيادات SaaS، ويُتحقق منه كما عند الإنشاء
        $sub = strtolower(trim($_POST['subdomain'] ?? '')) ?: null;
        if ($sub !== null && $errs = subdomain_errors($pdo, $sub, $id ?: null)) {
            flash(implode(' ', $errs), 'danger');
            redirect('clinics.php?edit=' . $id);
        }
        $custom = strtolower(trim($_POST['custom_domain'] ?? ''));
        if ($custom !== '' && !preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $custom)) {
            flash('النطاق المخصص غير صالح.', 'danger');
            redirect('clinics.php?edit=' . $id);
        }

        $data = [
            $name,
            trim($_POST['owner_name'] ?? ''),
            trim($_POST['phone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['site_url'] ?? ''),
            $sub,
            $custom,
            ((int)($_POST['plan_id'] ?? 0)) ?: null,
            $status,
            ($_POST['start_date'] ?? '') ?: null,
            ($_POST['expires_at'] ?? '') ?: null,
            trim($_POST['notes'] ?? ''),
        ];
        if ($id) {
            $pdo->prepare('UPDATE clinics SET name=?, owner_name=?, phone=?, email=?, site_url=?,
                           subdomain=?, custom_domain=?, plan_id=?, status=?, start_date=?,
                           expires_at=?, notes=? WHERE id=?')
                ->execute([...$data, $id]);
            log_action($pdo, 'update', 'clinic', $id, 'تعديل عيادة: ' . $name);
            flash('تم تحديث بيانات العيادة.');
        } else {
            $pdo->prepare('INSERT INTO clinics (name, owner_name, phone, email, site_url, subdomain,
                           custom_domain, plan_id, status, start_date, expires_at, notes, token)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
                ->execute([...$data, bin2hex(random_bytes(24))]);
            $id = (int)$pdo->lastInsertId();
            log_action($pdo, 'create', 'clinic', $id, 'إضافة عيادة: ' . $name);
            flash('تمت إضافة العيادة. افتح ملفها لنسخ رمز الربط.');
        }
        redirect('clinic.php?id=' . $id);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('SELECT name, db_name FROM clinics WHERE id = ?');
        $st->execute([$id]);
        $c = $st->fetch();
        if (!$c) {
            flash('العيادة غير موجودة.', 'danger');
            redirect('clinics.php');
        }

        // كتابة اسم العيادة يدويًا شرط للحذف: ضغطة واحدة لا تكفي لإزالة عميل
        if (trim($_POST['confirm_name'] ?? '') !== $c['name']) {
            flash('اكتب اسم العيادة بالضبط لتأكيد الحذف.', 'danger');
            redirect('clinics.php?edit=' . $id);
        }

        $dropDb = isset($_POST['drop_db']) && $c['db_name'] !== '';
        if ($dropDb) {
            /*
             * إسقاط قاعدة عيادة يمحو سجلات مرضى نهائيًا. لا يحدث إلا بطلب صريح
             * منفصل، وبعد أخذ نسخة احتياطية — وإلا يُكتفى بفك الارتباط فتبقى
             * القاعدة قابلة للاسترجاع.
             */
            if (!valid_db_name((string)$c['db_name'])) {
                flash('اسم قاعدة العيادة غير صالح — أُلغي الحذف.', 'danger');
                redirect('clinics.php?edit=' . $id);
            }
            try {
                $srv = server_pdo();
                $srv->exec('DROP DATABASE `' . $c['db_name'] . '`');
                log_action($pdo, 'drop_db', 'clinic', $id,
                    'إسقاط قاعدة العيادة ' . $c['db_name'] . ' — ' . $c['name']);
            } catch (Throwable $ex) {
                flash('تعذر إسقاط القاعدة: ' . $ex->getMessage(), 'danger');
                redirect('clinics.php?edit=' . $id);
            }
        }

        $pdo->prepare('DELETE FROM clinics WHERE id = ?')->execute([$id]);
        log_action($pdo, 'delete', 'clinic', $id, 'حذف عيادة: ' . $c['name']
            . ($dropDb ? ' (مع قاعدتها)' : ($c['db_name'] !== '' ? ' (القاعدة ' . $c['db_name'] . ' باقية)' : '')));
        flash($dropDb
            ? 'حُذفت العيادة وقاعدتها نهائيًا.'
            : 'حُذفت العيادة من الكونسول.' . ($c['db_name'] !== '' ? ' قاعدتها «' . $c['db_name'] . '» ما زالت على السيرفر.' : ''),
            'warning');
        redirect('clinics.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM clinics WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$showForm = $edit || isset($_GET['new']);

$q = trim($_GET['q'] ?? '');
$filter = $_GET['status'] ?? '';
$sql = 'SELECT c.*, p.name AS plan_name FROM clinics c LEFT JOIN plans p ON p.id = c.plan_id WHERE 1=1';
$args = [];
if ($q !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.owner_name LIKE ? OR c.phone LIKE ?)';
    array_push($args, "%$q%", "%$q%", "%$q%");
}
if (array_key_exists($filter, CLINIC_STATUS)) {
    $sql .= ' AND c.status = ?';
    $args[] = $filter;
}
$sql .= ' ORDER BY c.name';
$st = $pdo->prepare($sql);
$st->execute($args);
$clinics = $st->fetchAll();

$plans = $pdo->query('SELECT id, name, months, price FROM plans WHERE active = 1 ORDER BY months')->fetchAll();

page_header('العيادات', 'clinics.php');
?>
<div class="card">
    <div class="card-head">
        <h2>🏥 العيادات العميلة (<?= count($clinics) ?>)</h2>
        <a class="btn" href="clinics.php?new=1">+ إضافة عيادة</a>
    </div>
    <form class="inline-form" method="get">
        <input name="q" value="<?= e($q) ?>" placeholder="ابحث بالاسم أو صاحب العيادة أو الهاتف">
        <select name="status">
            <option value="">كل الحالات</option>
            <?php foreach (CLINIC_STATUS as $k => $l): ?>
                <option value="<?= e($k) ?>" <?= $filter === $k ? 'selected' : '' ?>><?= e($l) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-light" type="submit">بحث</button>
    </form>
    <div class="table-wrap"><table>
        <thead><tr><th>العيادة</th><th>الخطة</th><th>الحالة</th><th>ينتهي في</th><th>مرضى</th><th>آخر اتصال</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($clinics as $c): $d = days_until($c['expires_at']); ?>
            <tr>
                <td><a href="clinic.php?id=<?= (int)$c['id'] ?>"><strong><?= e($c['name']) ?></strong></a>
                    <?php if ($c['owner_name']): ?><br><small class="muted"><?= e($c['owner_name']) ?></small><?php endif; ?></td>
                <td><?= e($c['plan_name'] ?? '—') ?></td>
                <td><span class="badge <?= e(CLINIC_STATUS_BADGE[$c['status']]) ?>"><?= e(CLINIC_STATUS[$c['status']]) ?></span></td>
                <td class="num"><?= e(fmt_date($c['expires_at'])) ?>
                    <?php if ($d !== null && $d < 0 && in_array($c['status'], ['trial','active'], true)): ?>
                        <br><span class="badge bad">منتهٍ</span>
                    <?php elseif ($d !== null && $d <= EXPIRY_WARN_DAYS && $d >= 0): ?>
                        <br><span class="badge warn"><?= $d ?> يوم</span>
                    <?php endif; ?>
                </td>
                <td class="num"><?= (int)$c['patients_count'] ?></td>
                <td class="num"><?= $c['last_ping_at'] ? e(fmt_date($c['last_ping_at'])) : '—' ?></td>
                <td><a class="btn btn-light btn-sm" href="clinics.php?edit=<?= (int)$c['id'] ?>">تعديل</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$clinics): ?><tr><td colspan="7" class="muted">لا توجد عيادات مسجّلة.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<?php if ($showForm): $c = $edit ?: []; ?>
<div class="card">
    <h2><?= $edit ? 'تعديل: ' . e($c['name']) : 'عيادة جديدة' ?></h2>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><?php endif; ?>
        <div class="grid2">
            <label>اسم العيادة * <input name="name" value="<?= e($c['name'] ?? '') ?>" required></label>
            <label>صاحب العيادة <input name="owner_name" value="<?= e($c['owner_name'] ?? '') ?>" placeholder="د. أحمد محمود"></label>
            <label>الهاتف <input name="phone" value="<?= e($c['phone'] ?? '') ?>" dir="ltr"></label>
            <label>البريد <input type="email" name="email" value="<?= e($c['email'] ?? '') ?>" dir="ltr"></label>
        </div>
        <?php if (!empty($c['db_name'])): ?>
            <div class="grid2">
                <label>النطاق الفرعي (SaaS)
                    <input name="subdomain" value="<?= e($c['subdomain'] ?? '') ?>" dir="ltr"
                           pattern="[a-z0-9][a-z0-9-]{1,28}[a-z0-9]">
                    <small class="muted">تغييره يغيّر رابط دخول العيادة فورًا — بلّغ العميل.</small></label>
                <label>نطاق مخصص للعميل
                    <input name="custom_domain" value="<?= e($c['custom_domain'] ?? '') ?>" dir="ltr"
                           placeholder="clinic.example.com">
                    <small class="muted">يوجّهه العميل بـ CNAME لسيرفرك.</small></label>
            </div>
        <?php else: ?>
            <label>رابط نسخة العيادة <input name="site_url" value="<?= e($c['site_url'] ?? '') ?>" dir="ltr"
                placeholder="https://clinic.example.com"></label>
        <?php endif; ?>
        <div class="grid3">
            <label>الخطة
                <select name="plan_id">
                    <option value="">— بدون —</option>
                    <?php foreach ($plans as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" <?= (int)($c['plan_id'] ?? 0) === (int)$p['id'] ? 'selected' : '' ?>>
                            <?= e($p['name']) ?> — <?= e(money($p['price'])) ?> / <?= (int)$p['months'] ?> شهر</option>
                    <?php endforeach; ?>
                </select></label>
            <label>الحالة
                <select name="status">
                    <?php foreach (CLINIC_STATUS as $k => $l): ?>
                        <option value="<?= e($k) ?>" <?= ($c['status'] ?? 'trial') === $k ? 'selected' : '' ?>><?= e($l) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label>بداية التعامل <input type="date" name="start_date" value="<?= e($c['start_date'] ?? date('Y-m-d')) ?>"></label>
        </div>
        <label>ينتهي الاشتراك في
            <input type="date" name="expires_at" value="<?= e($c['expires_at'] ?? '') ?>">
            <small class="muted">يُمدَّد تلقائيًا عند سداد الفاتورة — لا تعدّله يدويًا إلا للتصحيح.</small></label>
        <label>ملاحظات <textarea name="notes"><?= e($c['notes'] ?? '') ?></textarea></label>
        <div class="actions">
            <button class="btn" type="submit">حفظ</button>
            <a class="btn btn-light" href="clinics.php">إلغاء</a>
        </div>
    </form>
</div>

<?php if ($edit): ?>
<div class="card" style="border-color:#fecaca">
    <h2>🗑️ حذف العيادة</h2>
    <p class="muted">يحذف العيادة وفواتيرها ومدفوعاتها من الكونسول.
        <?php if (!empty($c['db_name'])): ?>
            قاعدة بياناتها <code dir="ltr"><?= e($c['db_name']) ?></code> <strong>تبقى على السيرفر</strong>
            إلا إذا طلبت إسقاطها صراحةً — وفيها سجلات مرضى، فخُذ نسخة احتياطية أولًا.
        <?php endif; ?>
    </p>
    <form method="post" onsubmit="return confirm('تأكيد نهائي: حذف «<?= e($c['name']) ?>»؟')">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
        <label>اكتب اسم العيادة للتأكيد
            <input name="confirm_name" placeholder="<?= e($c['name']) ?>" autocomplete="off" required></label>
        <?php if (!empty($c['db_name'])): ?>
            <label style="display:flex;align-items:center;gap:8px">
                <input type="checkbox" name="drop_db" style="width:auto">
                أسقِط قاعدة البيانات أيضًا — <strong>محو نهائي لسجلات المرضى</strong>
            </label>
        <?php endif; ?>
        <button class="btn btn-danger" type="submit">حذف نهائيًا</button>
    </form>
</div>
<?php endif; ?>
<?php endif; ?>
<?php page_footer();
