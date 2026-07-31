<?php
require __DIR__ . '/inc/bootstrap.php';
require_perm('users.manage');

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

        // صلاحيات مخصصة، أو null لاستخدام افتراضي الدور
        $permsJson = null;
        if (($_POST['perm_mode'] ?? 'role') === 'custom') {
            $picked = array_values(array_intersect((array)($_POST['perms'] ?? []), all_perms()));
            $permsJson = json_encode($picked, JSON_UNESCAPED_UNICODE);
        }

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
            $pdo->prepare('UPDATE users SET name=?, username=?, role=?, active=?, perms=? WHERE id=?')
                ->execute([$name, $username, $role, $active, $permsJson, $uid]);
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
            $pdo->prepare('INSERT INTO users (name, username, password, role, active, perms) VALUES (?,?,?,?,?,?)')
                ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $active, $permsJson]);
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
$u = ['id' => 0, 'name' => '', 'username' => '', 'role' => 'reception', 'active' => 1, 'perms' => null];
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

        <?php
        $isAdminUser = ($u['role'] ?? '') === 'admin';
        $customPerms = is_string($u['perms'] ?? null) && $u['perms'] !== '';
        $granted = $customPerms
            ? array_flip((array)json_decode((string)$u['perms'], true))
            : array_flip(role_perms((string)($u['role'] ?? 'reception')));
        ?>
        <h3 class="form-section">🔐 الصلاحيات</h3>
        <?php if ($isAdminUser): ?>
            <div class="alert alert-warning">حساب المدير يملك كل الصلاحيات دائمًا ولا يمكن تقييده —
                لتقييد حساب غيّر دوره إلى «أخصائي تغذية» أو «استقبال».</div>
        <?php else: ?>
        <div class="perm-mode">
            <label style="display:flex;align-items:center;gap:8px;margin:0">
                <input type="radio" name="perm_mode" value="role" style="width:auto"
                       <?= $customPerms ? '' : 'checked' ?> onchange="permMode(this)">
                استخدام الصلاحيات الافتراضية للدور
            </label>
            <label style="display:flex;align-items:center;gap:8px;margin:0">
                <input type="radio" name="perm_mode" value="custom" style="width:auto"
                       <?= $customPerms ? 'checked' : '' ?> onchange="permMode(this)">
                تخصيص الصلاحيات يدويًا
            </label>
        </div>

        <div id="perm-box" class="perm-box <?= $customPerms ? '' : 'locked' ?>">
            <div class="perm-tools">
                <button class="btn btn-light btn-sm" type="button" onclick="permAll(true)">تحديد الكل</button>
                <button class="btn btn-light btn-sm" type="button" onclick="permAll(false)">إلغاء الكل</button>
                <?php foreach (ROLES as $rk => $rv): if ($rk === 'admin') continue; ?>
                    <button class="btn btn-light btn-sm" type="button"
                            onclick='permPreset(<?= json_encode(role_perms($rk)) ?>)'>قالب: <?= e($rv) ?></button>
                <?php endforeach; ?>
            </div>
            <div class="perm-grid">
            <?php foreach (PERM_GROUPS as $groupName => $items): ?>
                <fieldset class="perm-group">
                    <legend><?= e($groupName) ?></legend>
                    <?php foreach ($items as $key => $label): ?>
                        <label class="perm-item">
                            <input type="checkbox" name="perms[]" value="<?= e($key) ?>"
                                   <?= isset($granted[$key]) ? 'checked' : '' ?>>
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
            <?php endforeach; ?>
            </div>
        </div>
        <script>
        function permMode(el) {
            document.getElementById('perm-box').classList.toggle('locked', el.value === 'role');
        }
        function permAll(on) {
            document.querySelectorAll('#perm-box input[type=checkbox]').forEach(function (c) { c.checked = on; });
        }
        function permPreset(list) {
            var set = {};
            list.forEach(function (p) { set[p] = 1; });
            document.querySelectorAll('#perm-box input[type=checkbox]').forEach(function (c) {
                c.checked = !!set[c.value];
            });
            document.querySelector('input[name=perm_mode][value=custom]').checked = true;
            document.getElementById('perm-box').classList.remove('locked');
        }
        </script>
        <?php endif; ?>
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
        <thead><tr><th>الاسم</th><th>اسم الدخول</th><th>الدور</th><th>الصلاحيات</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><strong><?= e($r['name']) ?></strong><?= (int)$r['id'] === (int)user()['id'] ? ' <small class="muted">(أنت)</small>' : '' ?></td>
                <td class="num" dir="ltr"><?= e($r['username']) ?></td>
                <td><?= e(ROLES[$r['role']] ?? $r['role']) ?></td>
                <td>
                <?php
                if ($r['role'] === 'admin') {
                    echo '<span class="badge ok">كل الصلاحيات</span>';
                } elseif (is_string($r['perms']) && $r['perms'] !== '') {
                    $n = count((array)json_decode($r['perms'], true));
                    echo '<span class="badge info">مخصصة — ' . $n . ' من ' . count(all_perms()) . '</span>';
                } else {
                    echo '<span class="badge muted">افتراضي الدور (' . count(role_perms($r['role'])) . ')</span>';
                }
                ?>
                </td>
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
