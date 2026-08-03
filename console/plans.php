<?php
/** خطط الاشتراك. */
require __DIR__ . '/inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $pid = (int)($_POST['id'] ?? 0);
        $data = [
            trim($_POST['name'] ?? '') ?: 'خطة',
            max(1, (int)($_POST['months'] ?? 1)),
            max(0, (float)($_POST['price'] ?? 0)),
            trim($_POST['features'] ?? ''),
            isset($_POST['active']) ? 1 : 0,
        ];
        if ($pid) {
            $pdo->prepare('UPDATE plans SET name=?, months=?, price=?, features=?, active=? WHERE id=?')
                ->execute([...$data, $pid]);
            log_action($pdo, 'update', 'plan', $pid, 'تعديل خطة: ' . $data[0]);
            flash('تم تحديث الخطة.');
        } else {
            $pdo->prepare('INSERT INTO plans (name, months, price, features, active) VALUES (?,?,?,?,?)')
                ->execute($data);
            log_action($pdo, 'create', 'plan', (int)$pdo->lastInsertId(), 'إضافة خطة: ' . $data[0]);
            flash('تمت إضافة الخطة.');
        }
        redirect('plans.php');
    }
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM plans WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$plans = $pdo->query('SELECT p.*, (SELECT COUNT(*) FROM clinics c WHERE c.plan_id = p.id) AS clinics_n
                      FROM plans p ORDER BY p.months')->fetchAll();

page_header('خطط الاشتراك', 'plans.php');
?>
<div class="card">
    <div class="card-head">
        <h2>📦 الخطط</h2>
        <a class="btn" href="plans.php?new=1">+ خطة جديدة</a>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>الخطة</th><th>المدة</th><th>السعر</th><th>الشهري المكافئ</th><th>عيادات</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($plans as $p): ?>
            <tr>
                <td><strong><?= e($p['name']) ?></strong>
                    <?php if ($p['features']): ?><br><small class="muted"><?= e(str_replace("\n", ' · ', $p['features'])) ?></small><?php endif; ?></td>
                <td class="num"><?= (int)$p['months'] ?> شهر</td>
                <td class="num"><?= e(money($p['price'])) ?></td>
                <td class="num"><?= e(money((float)$p['price'] / max(1, (int)$p['months']))) ?></td>
                <td class="num"><?= (int)$p['clinics_n'] ?></td>
                <td><span class="badge <?= $p['active'] ? 'ok' : 'muted' ?>"><?= $p['active'] ? 'مفعّلة' : 'موقوفة' ?></span></td>
                <td><a class="btn btn-light btn-sm" href="plans.php?edit=<?= (int)$p['id'] ?>">تعديل</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div>

<?php if ($edit || isset($_GET['new'])): $p = $edit ?: ['active' => 1, 'months' => 1]; ?>
<div class="card">
    <h2><?= $edit ? 'تعديل: ' . e($p['name']) : 'خطة جديدة' ?></h2>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="action" value="save">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><?php endif; ?>
        <div class="grid3">
            <label>اسم الخطة <input name="name" value="<?= e($p['name'] ?? '') ?>" required placeholder="سنوي"></label>
            <label>المدة بالشهور <input type="number" min="1" name="months" value="<?= (int)($p['months'] ?? 1) ?>" required></label>
            <label>السعر <input type="number" step="0.01" min="0" name="price" value="<?= e($p['price'] ?? '') ?>" required></label>
        </div>
        <label>ما تشمله (سطر لكل ميزة) <textarea name="features" rows="4"><?= e($p['features'] ?? '') ?></textarea></label>
        <label style="display:flex;align-items:center;gap:8px">
            <input type="checkbox" name="active" style="width:auto" <?= ($p['active'] ?? 1) ? 'checked' : '' ?>> مفعّلة
        </label>
        <div class="actions">
            <button class="btn" type="submit">حفظ</button>
            <a class="btn btn-light" href="plans.php">إلغاء</a>
        </div>
    </form>
</div>
<?php endif; ?>
<?php page_footer();
