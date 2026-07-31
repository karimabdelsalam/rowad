<?php
require __DIR__ . '/inc/bootstrap.php';
require_login();
require_perm('plan.view');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_tpl') {
        deny_unless('plan.manage', 'plans.php');
        $tid = (int)($_POST['tid'] ?? 0);
        $cat = array_key_exists($_POST['category'] ?? '', DIET_CATS) ? $_POST['category'] : 'weight';
        $tag = array_key_exists($_POST['tag'] ?? '', DIET_TAGS) ? $_POST['tag'] : '';
        $data = [
            trim($_POST['title'] ?? '') ?: 'قالب بدون عنوان',
            $cat, $tag,
            trim($_POST['description'] ?? ''),
            ($_POST['calories'] ?? '') !== '' ? (int)$_POST['calories'] : null,
            trim($_POST['breakfast'] ?? ''),
            trim($_POST['snack1'] ?? ''),
            trim($_POST['lunch'] ?? ''),
            trim($_POST['snack2'] ?? ''),
            trim($_POST['dinner'] ?? ''),
            trim($_POST['forbidden'] ?? '') ?: null,
            trim($_POST['notes'] ?? ''),
            trim($_POST['warnings'] ?? '') ?: null,
        ];
        if ($tid) {
            $st = $pdo->prepare('UPDATE diet_templates SET title=?, category=?, tag=?, description=?, calories=?, breakfast=?, snack1=?, lunch=?, snack2=?, dinner=?, forbidden=?, notes=?, warnings=? WHERE id=?');
            $st->execute([...$data, $tid]);
            flash('تم تحديث القالب.');
        } else {
            $st = $pdo->prepare('INSERT INTO diet_templates (title, category, tag, description, calories, breakfast, snack1, lunch, snack2, dinner, forbidden, notes, warnings) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $st->execute($data);
            flash('تم إنشاء القالب.');
        }
        redirect('plans.php');
    }

    if ($action === 'del_tpl') {
        deny_unless('plan.delete', 'plans.php');
        $pdo->prepare('DELETE FROM diet_templates WHERE id = ?')->execute([(int)$_POST['tid']]);
        flash('تم حذف القالب.');
        redirect('plans.php');
    }

    if ($action === 'del_plan') {
        deny_unless('plan.delete', 'plans.php');
        $pdo->prepare('DELETE FROM diet_plans WHERE id = ?')->execute([(int)$_POST['plid']]);
        flash('تم حذف النظام الغذائي.');
        redirect('plans.php');
    }
}

$editTpl = null;
if (isset($_GET['tpl']) && can('plan.manage')) {
    $st = $pdo->prepare('SELECT * FROM diet_templates WHERE id = ?');
    $st->execute([(int)$_GET['tpl']]);
    $editTpl = $st->fetch() ?: null;
}
$showTplForm = $editTpl || isset($_GET['new_tpl']);

$plans = $pdo->query(
    'SELECT d.id, d.title, d.start_date, d.end_date, d.calories, p.id AS pid, p.name AS pname
     FROM diet_plans d JOIN patients p ON p.id = d.patient_id
     ORDER BY d.id DESC LIMIT 50'
)->fetchAll();
$templates = $pdo->query('SELECT * FROM diet_templates ORDER BY title')->fetchAll();

page_header('الأنظمة الغذائية', 'plans.php');
?>
<div class="card">
    <div class="card-head">
        <h2>🥗 آخر الأنظمة الغذائية</h2>
        <a class="btn" href="plan_edit.php">+ نظام غذائي جديد</a>
    </div>
    <div class="table-wrap"><table>
        <thead><tr><th>المريض</th><th>العنوان</th><th>من</th><th>إلى</th><th>السعرات</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($plans as $pl): ?>
            <tr>
                <td><a href="patient.php?id=<?= (int)$pl['pid'] ?>&tab=plans"><?= e($pl['pname']) ?></a></td>
                <td><strong><?= e($pl['title']) ?></strong></td>
                <td class="num"><?= e(fmt_date($pl['start_date'])) ?></td>
                <td class="num"><?= e(fmt_date($pl['end_date'])) ?></td>
                <td class="num"><?= $pl['calories'] ? e($pl['calories']) : '—' ?></td>
                <td><div class="actions">
                    <a class="btn btn-sm" href="plan_print.php?id=<?= (int)$pl['id'] ?>">🖨️ طباعة</a>
                    <a class="btn btn-light btn-sm" href="plan_edit.php?id=<?= (int)$pl['id'] ?>">تعديل</a>
                    <?php if (can('plan.delete')): ?>
                    <form method="post" data-confirm="حذف هذا النظام الغذائي؟">
                        <?= csrf_field() ?><input type="hidden" name="action" value="del_plan">
                        <input type="hidden" name="plid" value="<?= (int)$pl['id'] ?>">
                        <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                    </form>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$plans): ?><tr><td colspan="6" class="muted">لا توجد أنظمة غذائية بعد.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>📋 القوالب الجاهزة</h2>
        <?php if (can('plan.manage')): ?><a class="btn btn-sm" href="plans.php?new_tpl=1">+ قالب جديد</a><?php endif; ?>
    </div>
    <p class="muted">القوالب توفر وقتك: عند إنشاء نظام غذائي لمريض ابدأ من برنامج جاهز ثم عدّله على حالته.</p>
    <?php
    $byCat = [];
    foreach ($templates as $t) {
        $byCat[$t['category'] ?? 'weight'][] = $t;
    }
    foreach (DIET_CATS as $catKey => $catLabel):
        if (empty($byCat[$catKey])) continue; ?>
        <h3 class="form-section"><?= DIET_CAT_ICONS[$catKey] ?? '' ?> <?= e($catLabel) ?>
            <small class="muted">(<?= count($byCat[$catKey]) ?>)</small></h3>
        <div class="table-wrap"><table>
            <thead><tr><th>البرنامج</th><th>الحالة/الهدف</th><th>السعرات</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($byCat[$catKey] as $t): ?>
                <tr>
                    <td><strong><?= e($t['title']) ?></strong>
                        <?php if (!empty($t['description'])): ?><br><small class="muted"><?= e($t['description']) ?></small><?php endif; ?>
                        <?php if (!empty($t['warnings'])): ?><br><span class="badge warn">⚠ يحمل تحذيرات طبية</span><?php endif; ?>
                    </td>
                    <td><?= $t['tag'] ? '<span class="badge info">' . e(diet_tag_label($t['tag'])) . '</span>' : '<span class="muted">—</span>' ?></td>
                    <td class="num"><?= $t['calories'] ? e($t['calories']) . ' سعر' : '<span class="muted">مرن</span>' ?></td>
                    <td><div class="actions">
                        <a class="btn btn-sm" href="plan_edit.php?tpl=<?= (int)$t['id'] ?>">استخدام لمريض</a>
                        <?php if (can('plan.manage')): ?>
                        <a class="btn btn-light btn-sm" href="plans.php?tpl=<?= (int)$t['id'] ?>">تعديل</a>
                        <?php endif; ?>
                        <?php if (can('plan.delete')): ?>
                        <form method="post" data-confirm="حذف هذا القالب؟">
                            <?= csrf_field() ?><input type="hidden" name="action" value="del_tpl">
                            <input type="hidden" name="tid" value="<?= (int)$t['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">حذف</button>
                        </form>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endforeach; ?>
    <?php if (!$templates): ?><p class="muted">لا توجد قوالب.</p><?php endif; ?>
</div>

<?php if ($showTplForm && can('plan.manage')): $t = $editTpl ?: []; ?>
<div class="card" id="tpl-form">
    <h2><?= $editTpl ? 'تعديل قالب: ' . e($t['title']) : 'قالب جديد' ?></h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_tpl">
        <input type="hidden" name="tid" value="<?= (int)($t['id'] ?? 0) ?>">
        <div class="grid4">
            <label>العنوان * <input name="title" value="<?= e($t['title'] ?? '') ?>" required></label>
            <label>التصنيف
                <select name="category">
                    <?php foreach (DIET_CATS as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= ($t['category'] ?? 'weight') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>الحالة / الهدف
                <select name="tag">
                    <option value="">— عام —</option>
                    <?php foreach (DIET_TAGS as $k => [$label, $_]): ?>
                        <option value="<?= e($k) ?>" <?= ($t['tag'] ?? '') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>السعرات الحرارية <input type="number" min="0" name="calories" value="<?= e($t['calories'] ?? '') ?>"></label>
        </div>
        <label>وصف مختصر <input name="description" value="<?= e($t['description'] ?? '') ?>"
            placeholder="لمن يصلح هذا البرنامج وما الذي يستهدفه"></label>
        <div class="grid2">
            <label>الفطار <textarea name="breakfast"><?= e($t['breakfast'] ?? '') ?></textarea></label>
            <label>سناك صباحي <textarea name="snack1"><?= e($t['snack1'] ?? '') ?></textarea></label>
            <label>الغداء <textarea name="lunch"><?= e($t['lunch'] ?? '') ?></textarea></label>
            <label>سناك مسائي <textarea name="snack2"><?= e($t['snack2'] ?? '') ?></textarea></label>
            <label>العشاء <textarea name="dinner"><?= e($t['dinner'] ?? '') ?></textarea></label>
            <label>🚫 الممنوعات <textarea name="forbidden"><?= e($t['forbidden'] ?? '') ?></textarea></label>
            <label>تعليمات عامة <textarea name="notes"><?= e($t['notes'] ?? '') ?></textarea></label>
        </div>
        <label>⚠ تحذيرات طبية (تظهر للأخصائي وتُطبع مع النظام)
            <textarea name="warnings" rows="3"><?= e($t['warnings'] ?? '') ?></textarea></label>
        <div class="actions">
            <button class="btn" type="submit">حفظ القالب</button>
            <a class="btn btn-light" href="plans.php">إلغاء</a>
        </div>
    </form>
</div>
<script>document.getElementById('tpl-form').scrollIntoView();</script>
<?php endif;
page_footer();
