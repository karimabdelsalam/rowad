<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();

$planId = (int)($_GET['id'] ?? 0);
$plan = null;
if ($planId) {
    $st = $pdo->prepare('SELECT * FROM diet_plans WHERE id = ?');
    $st->execute([$planId]);
    $plan = $st->fetch();
    if (!$plan) {
        flash('النظام الغذائي غير موجود.', 'danger');
        redirect('plans.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $pid = $plan ? (int)$plan['patient_id'] : posted_patient_id($pdo);
    if (!$pid) {
        flash('اختر المريض من قائمة البحث.', 'danger');
        redirect('plan_edit.php');
    }
    $data = [
        trim($_POST['title'] ?? '') ?: 'نظام غذائي',
        ($_POST['start_date'] ?? '') ?: date('Y-m-d'),
        ($_POST['end_date'] ?? '') ?: null,
        ($_POST['calories'] ?? '') !== '' ? (int)$_POST['calories'] : null,
        trim($_POST['breakfast'] ?? ''),
        trim($_POST['snack1'] ?? ''),
        trim($_POST['lunch'] ?? ''),
        trim($_POST['snack2'] ?? ''),
        trim($_POST['dinner'] ?? ''),
        trim($_POST['forbidden'] ?? ''),
        trim($_POST['notes'] ?? ''),
    ];
    if ($plan) {
        $st = $pdo->prepare(
            'UPDATE diet_plans SET title=?, start_date=?, end_date=?, calories=?, breakfast=?, snack1=?,
             lunch=?, snack2=?, dinner=?, forbidden=?, notes=? WHERE id=?'
        );
        $st->execute([...$data, $planId]);
        flash('تم تحديث النظام الغذائي.');
    } else {
        $st = $pdo->prepare(
            'INSERT INTO diet_plans (patient_id, title, start_date, end_date, calories, breakfast, snack1,
             lunch, snack2, dinner, forbidden, notes, created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $st->execute([$pid, ...$data, user()['id']]);
        $planId = (int)$pdo->lastInsertId();
        flash('تم إنشاء النظام الغذائي.');
    }
    redirect('plan_print.php?id=' . $planId);
}

// تعبئة مسبقة: من خطة موجودة، أو من قالب، أو فارغ
$tpl = null;
if (!$plan && isset($_GET['tpl'])) {
    $st = $pdo->prepare('SELECT * FROM diet_templates WHERE id = ?');
    $st->execute([(int)$_GET['tpl']]);
    $tpl = $st->fetch() ?: null;
}
$src = $plan ?: $tpl ?: [];
$val = fn(string $k) => e($src[$k] ?? '');
$prefPatient = $plan ? (int)$plan['patient_id'] : (isset($_GET['patient']) ? (int)$_GET['patient'] : null);
$templates = $pdo->query('SELECT id, title FROM diet_templates ORDER BY title')->fetchAll();

$patientName = '';
if ($plan) {
    $st = $pdo->prepare('SELECT name FROM patients WHERE id = ?');
    $st->execute([(int)$plan['patient_id']]);
    $patientName = (string)$st->fetchColumn();
}

page_header($plan ? 'تعديل نظام غذائي' : 'نظام غذائي جديد', 'plans.php');
?>
<?php if (!$plan && $templates): ?>
<div class="card">
    <form class="inline-form" method="get">
        <?php if ($prefPatient): ?><input type="hidden" name="patient" value="<?= $prefPatient ?>"><?php endif; ?>
        <label>ابدأ من قالب جاهز
            <select name="tpl">
                <option value="">— بدون قالب —</option>
                <?php foreach ($templates as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= $tpl && (int)$tpl['id'] === (int)$t['id'] ? 'selected' : '' ?>><?= e($t['title']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn btn-light" type="submit">تعبئة من القالب</button>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <?= csrf_field() ?>
        <h3 class="form-section">بيانات النظام</h3>
        <div class="grid2">
            <?php if ($plan): ?>
                <label>المريض <input value="<?= e($patientName) ?>" disabled></label>
            <?php else: ?>
                <label>المريض * <?= patient_picker($pdo, 'patient_id', $prefPatient) ?></label>
            <?php endif; ?>
            <label>عنوان النظام * <input name="title" value="<?= $plan || $tpl ? $val('title') : '' ?>" required placeholder="مثال: نظام 1200 سعر — الأسبوع الأول"></label>
        </div>
        <div class="grid3">
            <label>تاريخ البداية <input type="date" name="start_date" value="<?= e($plan['start_date'] ?? date('Y-m-d')) ?>" required></label>
            <label>تاريخ النهاية <input type="date" name="end_date" value="<?= e($plan['end_date'] ?? '') ?>"></label>
            <label>السعرات الحرارية <input type="number" min="0" name="calories" value="<?= $val('calories') ?>"></label>
        </div>
        <h3 class="form-section">الوجبات</h3>
        <div class="grid2">
            <label>🍳 الفطار <textarea name="breakfast"><?= $val('breakfast') ?></textarea></label>
            <label>🍎 سناك صباحي <textarea name="snack1"><?= $val('snack1') ?></textarea></label>
            <label>🍗 الغداء <textarea name="lunch"><?= $val('lunch') ?></textarea></label>
            <label>🥜 سناك مسائي <textarea name="snack2"><?= $val('snack2') ?></textarea></label>
            <label>🥗 العشاء <textarea name="dinner"><?= $val('dinner') ?></textarea></label>
            <label>🚫 الممنوعات <textarea name="forbidden"><?= e($plan['forbidden'] ?? '') ?></textarea></label>
        </div>
        <label>تعليمات عامة (ماء، رياضة، مواعيد الوجبات…) <textarea name="notes"><?= $val('notes') ?></textarea></label>
        <div class="actions">
            <button class="btn" type="submit">حفظ والانتقال للطباعة</button>
            <a class="btn btn-light" href="plans.php">إلغاء</a>
        </div>
    </form>
</div>
<?php page_footer();
