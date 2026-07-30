<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('اسم المريض مطلوب.', 'danger');
            redirect('patients.php' . ($id ? '?edit=' . $id : '?new=1'));
        }
        $data = [
            $name,
            trim($_POST['phone'] ?? ''),
            ($_POST['gender'] ?? 'female') === 'male' ? 'male' : 'female',
            ($_POST['birth_date'] ?? '') ?: null,
            ($_POST['height_cm'] ?? '') !== '' ? (float)$_POST['height_cm'] : null,
            trim($_POST['job'] ?? ''),
            trim($_POST['address'] ?? ''),
            trim($_POST['medical_conditions'] ?? ''),
            trim($_POST['allergies'] ?? ''),
            trim($_POST['goal'] ?? ''),
            trim($_POST['notes'] ?? ''),
        ];
        if ($id) {
            $st = $pdo->prepare(
                'UPDATE patients SET name=?, phone=?, gender=?, birth_date=?, height_cm=?, job=?,
                 address=?, medical_conditions=?, allergies=?, goal=?, notes=? WHERE id=?'
            );
            $st->execute([...$data, $id]);
            flash('تم تحديث بيانات المريض.');
        } else {
            $st = $pdo->prepare(
                'INSERT INTO patients (name, phone, gender, birth_date, height_cm, job, address,
                 medical_conditions, allergies, goal, notes) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute($data);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE patients SET code = CONCAT('P-', LPAD(id, 4, '0')) WHERE id = ?")->execute([$id]);
            flash('تم تسجيل المريض بنجاح.');
        }
        redirect('patient.php?id=' . $id);
    }

    if ($action === 'delete' && has_role('admin')) {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM patients WHERE id = ?')->execute([$id]);
        flash('تم حذف المريض وكل بياناته.');
        redirect('patients.php');
    }
}

$editId = (int)($_GET['edit'] ?? 0);
$showForm = isset($_GET['new']) || $editId;
$p = [
    'id' => 0, 'name' => '', 'phone' => '', 'gender' => 'female', 'birth_date' => '',
    'height_cm' => '', 'job' => '', 'address' => '', 'medical_conditions' => '',
    'allergies' => '', 'goal' => '', 'notes' => '',
];
if ($editId) {
    $st = $pdo->prepare('SELECT * FROM patients WHERE id = ?');
    $st->execute([$editId]);
    $p = $st->fetch() ?: null;
    if (!$p) {
        flash('المريض غير موجود.', 'danger');
        redirect('patients.php');
    }
}

page_header($showForm ? ($editId ? 'تعديل بيانات مريض' : 'تسجيل مريض جديد') : 'المرضى', 'patients.php');

if ($showForm): ?>
<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <h3 class="form-section">البيانات الأساسية</h3>
        <div class="grid3">
            <label>الاسم * <input name="name" value="<?= e($p['name']) ?>" required></label>
            <label>الهاتف <input name="phone" value="<?= e($p['phone']) ?>" dir="ltr"></label>
            <label>النوع
                <select name="gender">
                    <option value="female" <?= $p['gender'] === 'female' ? 'selected' : '' ?>>أنثى</option>
                    <option value="male" <?= $p['gender'] === 'male' ? 'selected' : '' ?>>ذكر</option>
                </select>
            </label>
            <label>تاريخ الميلاد <input type="date" name="birth_date" value="<?= e($p['birth_date']) ?>"></label>
            <label>الطول (سم) <input type="number" step="0.1" min="0" name="height_cm" value="<?= e($p['height_cm']) ?>"></label>
            <label>الوظيفة <input name="job" value="<?= e($p['job']) ?>"></label>
        </div>
        <label>العنوان <input name="address" value="<?= e($p['address']) ?>"></label>
        <h3 class="form-section">الحالة الصحية</h3>
        <div class="grid2">
            <label>أمراض مزمنة / حالة طبية <textarea name="medical_conditions" placeholder="سكر، ضغط، غدة درقية…"><?= e($p['medical_conditions']) ?></textarea></label>
            <label>حساسية من أطعمة أو أدوية <textarea name="allergies"><?= e($p['allergies']) ?></textarea></label>
        </div>
        <div class="grid2">
            <label>الهدف <input name="goal" value="<?= e($p['goal']) ?>" placeholder="إنقاص 15 كجم، زيادة كتلة عضلية…"></label>
            <label>ملاحظات <input name="notes" value="<?= e($p['notes']) ?>"></label>
        </div>
        <div class="actions">
            <button class="btn" type="submit">حفظ</button>
            <a class="btn btn-light" href="<?= $editId ? 'patient.php?id=' . $editId : 'patients.php' ?>">إلغاء</a>
        </div>
    </form>
</div>
<?php else:
    $q = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per = 30;
    $where = '';
    $args = [];
    if ($q !== '') {
        $where = 'WHERE name LIKE ? OR phone LIKE ? OR code LIKE ?';
        $args = ["%$q%", "%$q%", "%$q%"];
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM patients $where");
    $st->execute($args);
    $total = (int)$st->fetchColumn();
    $pages = max(1, (int)ceil($total / $per));
    $page = min($page, $pages);

    $st = $pdo->prepare(
        "SELECT p.*, (SELECT weight FROM measurements m WHERE m.patient_id = p.id ORDER BY mdate DESC, id DESC LIMIT 1) AS last_weight
         FROM patients p $where ORDER BY p.id DESC LIMIT $per OFFSET " . (($page - 1) * $per)
    );
    $st->execute($args);
    $rows = $st->fetchAll();
?>
<div class="card">
    <div class="card-head">
        <form class="inline-form" method="get">
            <input name="q" value="<?= e($q) ?>" placeholder="بحث بالاسم أو الهاتف أو الكود…" style="min-width:260px">
            <button class="btn btn-sm" type="submit">بحث</button>
            <?php if ($q !== ''): ?><a class="btn btn-light btn-sm" href="patients.php">إلغاء البحث</a><?php endif; ?>
        </form>
        <a class="btn" href="patients.php?new=1">+ مريض جديد</a>
    </div>
    <p class="muted">إجمالي النتائج: <?= $total ?></p>
    <div class="table-wrap"><table>
        <thead><tr><th>الكود</th><th>الاسم</th><th>الهاتف</th><th>النوع</th><th>العمر</th><th>آخر وزن</th><th>الهدف</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($rows as $r): $age = calc_age($r['birth_date']); ?>
            <tr>
                <td class="num"><?= e($r['code']) ?></td>
                <td><a href="patient.php?id=<?= (int)$r['id'] ?>"><strong><?= e($r['name']) ?></strong></a></td>
                <td class="num" dir="ltr"><?= e($r['phone']) ?></td>
                <td><?= $r['gender'] === 'male' ? 'ذكر' : 'أنثى' ?></td>
                <td class="num"><?= $age !== null ? $age . ' سنة' : '—' ?></td>
                <td class="num"><?= $r['last_weight'] !== null ? e($r['last_weight']) . ' كجم' : '—' ?></td>
                <td><?= e(mb_substr($r['goal'], 0, 40)) ?></td>
                <td><div class="actions">
                    <a class="btn btn-light btn-sm" href="patient.php?id=<?= (int)$r['id'] ?>">الملف</a>
                    <a class="btn btn-light btn-sm" href="patients.php?edit=<?= (int)$r['id'] ?>">تعديل</a>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?><tr><td colspan="8" class="muted">لا توجد نتائج.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($pages > 1): ?>
    <div class="actions" style="margin-top:12px">
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a class="btn btn-sm <?= $i === $page ? '' : 'btn-light' ?>" href="?q=<?= urlencode($q) ?>&page=<?= $i ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif;
page_footer();
