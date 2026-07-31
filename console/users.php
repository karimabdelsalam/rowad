<?php
/** مستخدمو الكونسول: إضافة مساعدين، تعطيل حسابات، وتغيير كلمات المرور. */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/totp.php';
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

    if ($action === 'totp_start') {
        // مفتاح جديد يُعرض للمستخدم ولا يُفعَّل إلا بعد إدخال رمز صحيح منه
        $_SESSION['totp_setup'] = totp_new_secret();
        redirect('users.php#totp');
    }

    if ($action === 'totp_enable') {
        $secret = (string)($_SESSION['totp_setup'] ?? '');
        if ($secret === '') {
            redirect('users.php');
        }
        if (!totp_verify($secret, (string)($_POST['code'] ?? ''))) {
            flash('الرمز غير صحيح — تأكد أن ساعة هاتفك مضبوطة وأعد المحاولة.', 'danger');
            redirect('users.php#totp');
        }
        $pdo->prepare('UPDATE console_users SET totp_secret = ? WHERE id = ?')
            ->execute([$secret, $myId]);
        unset($_SESSION['totp_setup']);
        log_action($pdo, 'update', 'console_user', $myId, 'فعّل التحقق بخطوتين');
        flash('تم تفعيل التحقق بخطوتين على حسابك ✔');
        redirect('users.php');
    }

    if ($action === 'totp_disable') {
        // الإيقاف يحتاج كلمة المرور: جلسة مفتوحة لا تكفي لنزع الحماية
        $st = $pdo->prepare('SELECT password FROM console_users WHERE id = ?');
        $st->execute([$myId]);
        if (!password_verify((string)($_POST['password'] ?? ''), (string)$st->fetchColumn())) {
            flash('كلمة المرور غير صحيحة.', 'danger');
            redirect('users.php');
        }
        $pdo->prepare('UPDATE console_users SET totp_secret = NULL WHERE id = ?')->execute([$myId]);
        log_action($pdo, 'update', 'console_user', $myId, 'أوقف التحقق بخطوتين');
        flash('أُوقف التحقق بخطوتين.', 'warning');
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

$my = $pdo->prepare('SELECT totp_secret FROM console_users WHERE id = ?');
$my->execute([$myId]);
$myTotpOn = (string)$my->fetchColumn() !== '';
$setupSecret = $_SESSION['totp_setup'] ?? null;

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

    <div class="card" id="totp">
        <h3>🛡️ التحقق بخطوتين (2FA)</h3>
        <?php if ($myTotpOn): ?>
            <div class="alert alert-success">مفعّل على حسابك ✔ — الدخول يحتاج كلمة المرور + رمزًا من تطبيق المصادقة.</div>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="totp_disable">
                <label>كلمة مرورك لتأكيد الإيقاف <input type="password" name="password" required dir="ltr"></label>
                <button class="btn btn-danger btn-sm" type="submit">إيقاف التحقق بخطوتين</button>
            </form>
        <?php elseif ($setupSecret): ?>
            <p class="muted">1) افتح تطبيق مصادقة (Google Authenticator أو مثله) واختر
                «إدخال مفتاح يدويًا»، أو افتح الرابط من هاتفك مباشرة:</p>
            <label>المفتاح <input value="<?= e($setupSecret) ?>" dir="ltr" readonly onclick="this.select()"
                style="font-family:monospace;letter-spacing:2px"></label>
            <label>أو الرابط <input value="<?= e(totp_uri($setupSecret, cuser()['name'], setting('brand_name', 'Planova'))) ?>"
                dir="ltr" readonly onclick="this.select()"></label>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="totp_enable">
                <label>2) أدخل الرمز الظاهر في التطبيق لتأكيد التفعيل
                    <input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6" dir="ltr"
                           style="text-align:center;font-size:20px;letter-spacing:6px"></label>
                <button class="btn" type="submit">تفعيل</button>
            </form>
        <?php else: ?>
            <p class="muted">طبقة حماية إضافية لحسابك: حتى لو تسربت كلمة مرورك، لا دخول
                بدون رمز من هاتفك يتغير كل 30 ثانية.</p>
            <form method="post">
                <?= csrf_field() ?><input type="hidden" name="action" value="totp_start">
                <button class="btn" type="submit">إعداد التحقق بخطوتين</button>
            </form>
        <?php endif; ?>
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
