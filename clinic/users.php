<?php
require __DIR__ . '/inc/bootstrap.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $uid = (int)($_POST['uid'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $role = array_key_exists($_POST['role'] ?? '', ROLES) ? $_POST['role'] : 'reception';
        $password = (string)($_POST['password'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($name === '' || $username === '') {
            flash('الاسم واسم الدخول مطلوبان.', 'danger');
            redirect('users.php' . ($uid ? '?edit=' . $uid : '?new=1'));
        }

        $st = $pdo->prepare('SELECT id FROM users WHERE username = ? AND id != ?');
        $st->execute([$username, $uid]);
        if ($st->fetch()) {
            flash('اسم الدخول مستخدم بالفعل.', 'danger');
            redirect('users.php' . ($uid ? '?edit=' . $uid : '?new=1'));
        }

        if ($uid) {
            if ($uid === (int)user()['id']) {
                $role = 'admin';   // لا يمكن للمدير تغيير صلاحية نفسه
                $active = 1;       // ولا تعطيل حسابه
            }
            $pdo->prepare('UPDATE users SET name=?, username=?, role=?, active=? WHERE id=?')
                ->execute([$name, $username, $role, $active, $uid]);
            if ($password !== '') {
                if (mb_strlen($password) < 8) {
                    flash('كلمة المرور يجب ألا تقل عن 8 أحرف.', 'danger');
                    redirect('users.php?edit=' . $uid);
                }
                $pdo->prepare('UPDATE users SET password=? WHERE id=?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $uid]);
            }
            flash('تم تحديث المستخدم.');
        } else {
            if (mb_strlen($password) < 8) {
                flash('كلمة المرور يجب ألا تقل عن 8 أحرف.', 'danger');
                redirect('users.php?new=1');
            }
            $pdo->prepare('INSERT INTO users (name, username, password, role, active) VALUES (?,?,?,?,?)')
                ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $active]);
            flash('تم إنشاء المستخدم.');
        }
        redirect('users.php');
    }

    if ($action === 'delete') {
        $uid = (int)($_POST['uid'] ?? 0);
        if ($uid === (int)user()['id']) {
            flash('لا يمكنك حذف حسابك الحالي.', 'danger');
        } else {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
            flash('تم حذف المستخدم.');
        }
        redirect('users.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$showForm = isset($_GET['new']) || $editId;
$u = ['id' => 0, 'name' => '', 'username' => '', 'role' => 'reception', 'active' => 1];
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$editId]);
    $u = $st->fetch();
    if (!$u) {
        flash('المستخدم غير موجود.', 'danger');
        redirect('users.php');
    }
}

$rows = $pdo->query('SELECT * FROM users ORDER BY id')->fetchAll();

page_header('المستخدمون', 'users.php');

if ($showForm): ?>
<div class="card">
    <h2><?= $editId ? 'تعديل مستخدم: ' . e($u['name']) : 'مستخدم جديد' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="uid" value="<?= (int)$u['id'] ?>">
        <div class="grid2">
            <label>الاسم * <input name="name" value="<?= e($u['name']) ?>" required></label>
            <label>اسم الدخول * <input name="username" value="<?= e($u['username']) ?>" required dir="ltr"></label>
            <label>الصلاحية
                <select name="role" <?= $editId === (int)user()['id'] ? 'disabled' : '' ?>>
                    <?php foreach (ROLES as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= $u['role'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>كلمة المرور <?= $editId ? '(اتركها فارغة للإبقاء عليها)' : '*' ?>
                <input type="password" name="password" <?= $editId ? '' : 'required' ?> minlength="8" dir="ltr"></label>
        </div>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="active" style="width:auto" <?= $u['active'] ? 'checked' : '' ?> <?= $editId === (int)user()['id'] ? 'disabled' : '' ?>>
            الحساب مفعّل
        </label>
        <div class="actions">
            <button class="btn" type="submit">حفظ</button>
            <a class="btn btn-light" href="users.php">إلغاء</a>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h2>👤 المستخدمون</h2>
        <a class="btn" href="users.php?new=1">+ مستخدم جديد</a>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>الصلاحية</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong><?= (int)$r['id'] === (int)user()['id'] ? ' <small class="muted">(أنت)</small>' : '' ?></td>
                <td class="num" dir="ltr"><?= e($r['username']) ?></td>
                <td><?= e(ROLES[$r['role']] ?? $r['role']) ?></td>
                <td><span class="badge <?= $r['active'] ? 'ok' : 'muted' ?>"><?= $r['active'] ? 'مفعّل' : 'معطّل' ?></span></td>
                <td><div class="actions">
                    <a class="btn btn-light btn-sm" href="users.php?edit=<?= (int)$r['id'] ?>">تعديل</a>
                    <?php if ((int)$r['id'] !== (int)user()['id']): ?>
                    <form method="post" data-confirm="حذف هذا المستخدم؟">
                        <?= csrf_field() ?><input type="hidden" name="action" value="delete">
                        <input type="hidden" name="uid" value="<?= (int)$r['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                    </form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>
<?php page_footer();
