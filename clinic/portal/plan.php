<?php
require __DIR__ . '/inc/bootstrap.php';
portal_require_login();
$p = portal_load($pdo);
$id = (int)$p['id'];

$st = $pdo->prepare(
    'SELECT * FROM diet_plans WHERE patient_id = ? ORDER BY start_date DESC, id DESC LIMIT 1'
);
$st->execute([$id]);
$plan = $st->fetch();

portal_header('نظامي الغذائي', 'plan.php');

if (!$plan): ?>
    <div class="pcard"><p class="pmuted">لم يُسجَّل نظام غذائي بعد. سيظهر هنا فور إعداده من طبيبك.</p></div>
<?php else:
    $meals = [
        ['🍳', 'الفطار', $plan['breakfast']],
        ['🍎', 'سناك صباحي', $plan['snack1']],
        ['🍗', 'الغداء', $plan['lunch']],
        ['🥜', 'سناك مسائي', $plan['snack2']],
        ['🥗', 'العشاء', $plan['dinner']],
    ];
?>
    <div class="pcard phero">
        <div class="plabel">نظامك الحالي</div>
        <div class="pbig"><?= e($plan['title']) ?></div>
        <div class="pmuted">
            من <?= e(fmt_date($plan['start_date'])) ?><?= $plan['end_date'] ? ' إلى ' . e(fmt_date($plan['end_date'])) : '' ?>
            <?= $plan['calories'] ? ' · ' . e($plan['calories']) . ' سعر/يوم' : '' ?>
        </div>
    </div>

    <?php foreach ($meals as [$ico, $name, $content]): if (!trim((string)$content)) continue; ?>
    <div class="pcard pmeal">
        <div class="pmeal-h"><?= $ico ?> <?= e($name) ?></div>
        <div class="pmeal-b"><?= nl2br(e($content)) ?></div>
    </div>
    <?php endforeach; ?>

    <?php if (trim((string)$plan['forbidden'])): ?>
    <div class="pcard pdanger">
        <div class="pmeal-h">🚫 الممنوعات</div>
        <div class="pmeal-b"><?= nl2br(e($plan['forbidden'])) ?></div>
    </div>
    <?php endif; ?>

    <?php if (trim((string)$plan['notes'])): ?>
    <div class="pcard">
        <div class="pmeal-h">📌 تعليمات عامة</div>
        <div class="pmeal-b"><?= nl2br(e($plan['notes'])) ?></div>
    </div>
    <?php endif; ?>
<?php endif;
portal_footer();
