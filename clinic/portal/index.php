<?php
require __DIR__ . '/inc/bootstrap.php';
portal_require_login();
$p = portal_load($pdo);
$id = (int)$p['id'];

$st = $pdo->prepare(
    "SELECT a.*, u.name AS doctor_name FROM appointments a LEFT JOIN users u ON u.id = a.doctor_id
     WHERE a.patient_id = ? AND a.status = 'scheduled' AND a.adate >= CURDATE()
     ORDER BY a.adate, a.atime LIMIT 1"
);
$st->execute([$id]);
$nextAppt = $st->fetch();

$st = $pdo->prepare('SELECT * FROM measurements WHERE patient_id = ? ORDER BY mdate DESC, id DESC LIMIT 1');
$st->execute([$id]);
$last = $st->fetch();

$st = $pdo->prepare('SELECT weight FROM measurements WHERE patient_id = ? ORDER BY mdate ASC, id ASC LIMIT 1');
$st->execute([$id]);
$firstWeight = $st->fetchColumn();

$plan = active_plan($pdo, $id);
$nextDose = $plan ? next_dose_date($pdo, $plan) : null;
$packages = patient_active_packages($pdo, $id);
$balance = injection_balance($pdo, $id);

$st = $pdo->prepare('SELECT name FROM users WHERE id = ?');
$st->execute([(int)($p['doctor_id'] ?? 0)]);
$doctorName = (string)$st->fetchColumn();

$firstName = explode(' ', trim((string)$p['name']))[0];
portal_header('أهلاً ' . $firstName, 'index.php');
?>
<?php if ($nextAppt): ?>
<div class="pcard phero">
    <div class="plabel">موعدك القادم</div>
    <div class="pbig"><?= e(day_ar($nextAppt['adate'])) ?> — <?= e(fmt_time($nextAppt['atime'])) ?></div>
    <div class="pmuted"><?= e(fmt_date($nextAppt['adate'])) ?>
        · <?= e(APPT_TYPES[$nextAppt['type']] ?? '') ?>
        <?= $nextAppt['doctor_name'] ? ' · مع ' . e($nextAppt['doctor_name']) : '' ?></div>
</div>
<?php else: ?>
<div class="pcard">
    <div class="plabel">المواعيد</div>
    <p class="pmuted">لا يوجد موعد قادم محجوز. تواصل مع العيادة لحجز موعدك.</p>
    <?php if (setting('clinic_phone')): ?>
        <a class="pbtn" href="tel:<?= e(setting('clinic_phone')) ?>">📞 اتصل بالعيادة</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="pgrid">
    <?php if ($last): ?>
    <div class="pstat">
        <div class="plabel">وزنك الحالي</div>
        <div class="pvalue"><?= e(num_fmt($last['weight'])) ?> <small>كجم</small></div>
        <?php if ($firstWeight !== false && $firstWeight !== null):
            $diff = round((float)$last['weight'] - (float)$firstWeight, 1); ?>
            <div class="ptrend" style="color:<?= $diff <= 0 ? '#15803d' : '#b91c1c' ?>">
                <?= $diff <= 0 ? '▼' : '▲' ?> <?= e(num_fmt(abs($diff))) ?> كجم منذ البداية</div>
        <?php endif; ?>
    </div>
    <?php $bmi = calc_bmi($last['weight'], $p['height_cm']); if ($bmi !== null): [$bl, $bc] = bmi_label($bmi); ?>
    <div class="pstat">
        <div class="plabel">مؤشر كتلة الجسم</div>
        <div class="pvalue"><?= e((string)$bmi) ?></div>
        <div class="pbadge <?= e($bc) ?>"><?= e($bl) ?></div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php if ($p['goal']): ?>
    <div class="pstat">
        <div class="plabel">هدفك</div>
        <div class="pgoal"><?= e($p['goal']) ?></div>
    </div>
    <?php endif; ?>
</div>

<?php if ($plan && $nextDose): $overdue = $nextDose < date('Y-m-d'); ?>
<div class="pcard">
    <div class="plabel">💉 جرعتك القادمة</div>
    <div class="pbig <?= $overdue ? 'pdue' : '' ?>">
        <?= $overdue ? 'متأخرة — ' : '' ?><?= e(day_ar($nextDose)) ?> <?= e(fmt_date($nextDose)) ?>
    </div>
    <div class="pmuted"><?= e($plan['drug_name']) ?> — <?= e(num_fmt($plan['weekly_units'])) ?> وحدة</div>
</div>
<?php endif; ?>

<?php foreach ($packages as $pk): $left = (int)$pk['sessions_total'] - (int)$pk['used']; ?>
<div class="pcard">
    <div class="plabel">🎟️ <?= e($pk['name']) ?></div>
    <div class="pbig"><?= $left ?> <small>جلسة متبقية</small></div>
    <div class="pbar"><span style="width:<?= (int)round((int)$pk['used'] * 100 / max(1, (int)$pk['sessions_total'])) ?>%"></span></div>
    <div class="pmuted"><?= (int)$pk['used'] ?> من <?= (int)$pk['sessions_total'] ?> مستهلكة
        <?= $pk['expiry_date'] ? ' · تنتهي ' . e(fmt_date($pk['expiry_date'])) : '' ?></div>
</div>
<?php endforeach; ?>

<?php if ($balance > 0.005): ?>
<div class="pcard pwarn">
    <div class="plabel">مستحقات</div>
    <div class="pbig"><?= e(money($balance)) ?></div>
    <div class="pmuted">برجاء السداد في زيارتك القادمة — <a href="account.php">التفاصيل</a></div>
</div>
<?php endif; ?>

<?php if ($doctorName): ?>
<div class="pcard"><div class="plabel">طبيبك المعالج</div><div class="pbig"><?= e($doctorName) ?></div></div>
<?php endif; ?>

<?php portal_footer();
