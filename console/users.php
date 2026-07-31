<?php
/** مستخدمو الكونسول: إضافة مساعدين، تعطيل حسابات، وتغيير كلمات المرور. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

$myId = (int)cuser()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $username = strtolower(trim($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || !preg_match('/^[a-z0-9._]{3,30}$/', $username)) {
            flash('اكتب الاسم، واسم دخول من 3-30 حرفًا إنجليزيًا صغيرًا أو رقمًا أو نقطة.', 'danger');
            redirect('users.php');
        }
        if (mb_strlen($password) < 8) {
            flash('كلمة المرور 8 أحرف على الأقل.', 'danger');
            redirect('users.php');
        }
        $dup = $pdo->prepare('SELECT id FROM console_users WHERE username = ?');
        $dup->execute([$username]);
        if ($dup->fetchColumn()) {
            flash('اسم الدخول مستخدم بالفعل.', 'danger');
            redirect('users.php');
        }
        $pdo->prepare('INSERT INTO console_users (name, username, password) VALUES (?,?,?)')
            ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
        log_action($pdo, 'create', 'console_user', (int)$pdo->lastInsertId(), 'إضافة مستخدم كونسول: ' . $name);
        flash('تمت إضافة ' . $name . '.');
        redirect('users.php');
    }

    if ($action === 'toggle') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid === $myId) {
            flash('لا يمكنك تعطيل حسابك وأنت داخل به.', 'danger');
            redirect('users.php');
        }
        $st = $pdo->prepare('SELECT name, active FROM console_users WHERE id = ?');
        $st->execute([$uid]);
        if (!$u = $st->fetch()) {
            redirect('users.php');
        }
        // لا يُعطَّل آخر حساب نشط: كونسول بلا مدخل نهائيًا لا يصلحه إلا SQL يدوي
        if ($u['active']) {
            $others = $pdo->prepare('SELECT COUNT(*) FROM console_users WHERE active = 1 AND id <> ?');
            $others->execute([$uid]);
            if (!(int)$others->fetchColumn()) {
                flash('هذا آخر حساب نشط — أضف حسابًا آخر قبل تعطيله.', 'danger');
                redirect('users.php');
            }
        }
        $pdo->prepare('UPDATE console_users SET active = 1 - active WHERE id = ?')->execute([$uid]);
        log_action($pdo, 'update', 'console_user', $uid,
            ($u['active'] ? 'تعطيل' : 'تفعيل') . ' حساب: ' . $u['name']);
        flash($u['active'] ? 'عُطِّل الحساب وسيُطرد من جلسته فورًا.' : 'أُعيد تفعيل الحساب.');
        redirect('users.php');
    }

    if ($action === 'reset_pass') {
        $uid = (int)($_POST['uid'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        if (mb_strlen($password) < 8) {
            flash('كلمة المرور 8 أحرف على الأقل.', 'danger');
            redirect('users.php');
        }
        $st = $pdo->prepare('SELECT name FROM console_users WHERE id = ?');
        $st->execute([$uid]);
        if (!$name = $st->fetchColumn()) {
            redirect('users.php');
        }
        $pdo->prepare('UPDATE console_users SET password = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
        log_action($pdo, 'update', 'console_user', $uid, 'إعادة تعيين كلمة مرور: ' . $name);
        flash('تم تغيير كلمة مرور ' . $name . '.');
        redirect('users.php');
    }

    if ($action === 'my_pass') {
        $current = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');

        $st = $pdo->prepare('SELECT password FROM console_users WHERE id = ?');
        $st->execute([$myId]);
        // تغيير كلمة مروري يتطلب الحالية: جلسة متروكة مفتوحة لا تكفي للاستيلاء على الحساب
        if (!password_verify($current, (string)$st->fetchColumn())) {
            flash('كلمة المرور الحالية غير صحيحة.', 'danger');
            redirect('users.php');
        }
        if (mb_strlen($new) < 8) {
            flash('كلمة المرور الجديدة 8 أحرف على الأقل.', 'danger');
            redirect('users.php');
        }
        if (($_POST['new'] ?? '') !== ($_POST['new2'] ?? '')) {
            flash('كلمتا المرور غير متطابقتين.', 'danger');
            redirect('users.php');
        }
        $pdo->prepare('UPDATE console_users SET password = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $myId]);
        log_action($pdo, 'update', 'console_user', $myId, 'غيّر كلمة مروره بنفسه');
        flash('تم تغيير كلمة مرورك.');
        redirect('users.php');
    }
}

$users = $pdo->query('SELECT u.*, (SELECT MAX(l.created_at) FROM console_log l
                        WHERE l.user_id = u.id AND l.action = "login") AS last_login
                      FROM console_users u ORDER BY u.id')->fetchAll();

page_header('المستخدمون', 'users.php');
?>
<div class="card">
    <h2>👤 مستخدمو الكونسول (<?= count($users) ?>)</h2>
    <p class="muted">كل من يدخل هنا يرى عملاءك وفواتيرك كاملة — أضف من تثق به فقط.</p>
    <div class="table-wrap"><table>
        <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>آخر دخول</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><strong><?= e($u['name']) ?></strong>
                    <?= (int)$u['id'] === $myId ? '<span class="badge info">أنت</span>' : '' ?></td>
                <td dir="ltr"><?= e($u['username']) ?></td>
                <td class="num"><?= $u['last_login'] ? e(date('Y/m/d H:i', strtotime($u['last_login']))) : '—' ?></td>
                <td><span class="badge <?= $u['active'] ? 'ok' : 'muted' ?>"><?= $u['active'] ? 'نشط' : 'معطَّل' ?></span></td>
                <td><div class="actions">
                    <?php if ((int)$u['id'] !== $myId): ?>
                        <form method="post" onsubmit="return confirm('<?= $u['active'] ? 'تعطيل' : 'تفعيل' ?> حساب <?= e($u['name']) ?>؟')">
                            <?= csrf_field() ?><input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-light btn-sm" type="submit"><?= $u['active'] ? 'تعطيل' : 'تفعيل' ?></button>
                        </form>
                        <details style="display:inline-block">
                            <summary class="btn btn-light btn-sm" style="list-style:none;cursor:pointer">كلمة مرور جديدة</summary>
                            <form method="post" class="inline-form" style="margin-top:8px">
                                <?= csrf_field() ?><input type="hidden" name="action" value="reset_pass">
                                <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
                                <input type="password" name="password" minlength="8" required
                                       placeholder="8 أحرف على الأقل" dir="ltr" style="min-width:180px">
                                <button class="btn btn-sm" type="submit">تغيير</button>
                            </form>
                        </details>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<div class="grid2">
    <div class="card">
        <h3>➕ إضافة مستخدم</h3>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="create">
            <label>الاسم <input name="name" required></label>
            <label>اسم الدخول <input name="username" required dir="ltr" pattern="[a-z0-9._]{3,30}"></label>
            <label>كلمة المرور <input type="password" name="password" required minlength="8" dir="ltr"></label>
            <button class="btn" type="submit">إضافة</button>
        </form>
    </div>

    <div class="card">
        <h3>🔑 تغيير كلمة مروري</h3>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="action" value="my_pass">
            <label>كلمة المرور الحالية <input type="password" name="current" required dir="ltr"></label>
            <label>الجديدة (8 أحرف على الأقل) <input type="password" name="new" required minlength="8" dir="ltr"></label>
            <label>تأكيد الجديدة <input type="password" name="new2" required minlength="8" dir="ltr"></label>
            <button class="btn" type="submit">تغيير كلمة مروري</button>
        </form>
    </div>
</div>
<?php page_footer();
