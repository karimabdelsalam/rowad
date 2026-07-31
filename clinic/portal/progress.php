<?php
require __DIR__ . '/inc/bootstrap.php';
portal_require_login();
$p = portal_load($pdo);
$id = (int)$p['id'];

$st = $pdo->prepare('SELECT * FROM measurements WHERE patient_id = ? ORDER BY mdate, id');
$st->execute([$id]);
$measures = $st->fetchAll();

$first = $measures[0] ?? null;
$last = $measures ? end($measures) : null;

portal_header('متابعة وزني', 'progress.php');
?>
<?php if (!$measures): ?>
    <div class="pcard"><p class="pmuted">لم تُسجَّل قياسات بعد. ستظهر هنا بعد أول زيارة.</p></div>
<?php else:
    $diff = $first && $last ? round((float)$last['weight'] - (float)$first['weight'], 1) : 0; ?>
    <div class="pgrid">
        <div class="pstat">
            <div class="plabel">الوزن الحالي</div>
            <div class="pvalue"><?= e(num_fmt($last['weight'])) ?> <small>كجم</small></div>
        </div>
        <div class="pstat">
            <div class="plabel">وزن البداية</div>
            <div class="pvalue"><?= e(num_fmt($first['weight'])) ?> <small>كجم</small></div>
        </div>
        <div class="pstat">
            <div class="plabel">التغيّر</div>
            <div class="pvalue" style="color:<?= $diff <= 0 ? '#15803d' : '#b91c1c' ?>">
                <?= $diff > 0 ? '+' : '' ?><?= e(num_fmt($diff)) ?> <small>كجم</small></div>
        </div>
    </div>

    <div class="pcard">
        <div class="plabel">منحنى الوزن</div>
        <?= weight_chart_svg($measures) ?>
    </div>

    <div class="pcard">
        <div class="plabel">سجل القياسات</div>
        <div class="ptable-wrap"><table class="ptable">
            <thead><tr><th>التاريخ</th><th>الوزن</th><th>BMI</th><th>دهون %</th><th>الوسط</th></tr></thead>
            <tbody>
            <?php foreach (array_reverse($measures) as $m): $bmi = calc_bmi($m['weight'], $p['height_cm']); ?>
                <tr>
                    <td><?= e(fmt_date($m['mdate'])) ?></td>
                    <td><strong><?= e(num_fmt($m['weight'])) ?></strong></td>
                    <td><?= $bmi !== null ? e((string)$bmi) : '—' ?></td>
                    <td><?= $m['body_fat'] !== null ? e(num_fmt($m['body_fat'])) : '—' ?></td>
                    <td><?= $m['waist'] !== null ? e(num_fmt($m['waist'])) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>
<?php endif;
portal_footer();
