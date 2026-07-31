<?php
/**
 * صفحة الأسعار العامة — نقطة الدخول التسويقية.
 *
 * تعرض الخطط المفعّلة وتوصّل الزائر لصفحة التسجيل بالخطة التي اختارها.
 */
require __DIR__ . '/inc/bootstrap.php';

$brand = setting('brand_name', 'نظام إدارة العيادات');
$open = setting('signup_open', '0') === '1' && setting('base_domain', '') !== '';
$trialDays = max(0, (int)setting('trial_days', '14'));
$plans = $pdo->query('SELECT * FROM plans WHERE active = 1 ORDER BY months')->fetchAll();

// أرخص سعر شهري مكافئ، لتمييز الخطة الأوفر
$best = null;
foreach ($plans as $p) {
    $per = (float)$p['price'] / max(1, (int)$p['months']);
    if ($best === null || $per < $best) {
        $best = $per;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>الأسعار | <?= e($brand) ?></title>
<meta name="description" content="نظام إدارة عيادات التغذية والتخسيس — مرضى ومواعيد وأنظمة غذائية وحقن وباقات وتقارير.">
<link rel="stylesheet" href="assets/console.css">
<style>
.marketing { max-width: 1040px; margin: 0 auto; padding: 34px 18px 60px; }
.hero { text-align: center; margin-bottom: 34px; }
.hero h1 { font-size: 30px; margin-bottom: 10px; }
.hero p { color: var(--muted); font-size: 16px; max-width: 620px; margin: 0 auto; }
.feat { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin: 26px 0 38px; }
.feat div { background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); padding: 14px; font-size: 14px; }
.plans { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
.plan { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 22px; display: flex; flex-direction: column; }
.plan.best { border-color: var(--primary); border-width: 2px; position: relative; }
.plan .tag { position: absolute; top: -12px; inset-inline-start: 22px; background: var(--primary); color: #fff; font-size: 12px; font-weight: 700; padding: 3px 12px; border-radius: 999px; }
.plan h3 { font-size: 19px; margin-bottom: 6px; }
.plan .price { font-size: 30px; font-weight: 700; color: var(--primary); }
.plan .per { color: var(--muted); font-size: 13px; margin-bottom: 14px; }
.plan ul { list-style: none; margin: 0 0 18px; font-size: 14px; line-height: 2; }
.plan ul li::before { content: '✓'; color: var(--primary); font-weight: 700; margin-inline-end: 7px; }
.plan .btn { margin-top: auto; }
.note { text-align: center; color: var(--muted); font-size: 13.5px; margin-top: 30px; line-height: 2; }
</style>
</head>
<body>
<div class="marketing">

    <div class="hero">
        <h1>🍏 <?= e($brand) ?></h1>
        <p>نظام كامل بالعربي لإدارة عيادات التغذية والتخسيس: المرضى والقياسات والمواعيد
            والأنظمة الغذائية وحقن التخسيس والباقات والتقارير — يشتغل من المتصفح بدون تركيب.</p>
    </div>

    <div class="feat">
        <div>👥 <strong>ملف مريض كامل</strong><br><span class="muted">قياسات ومنحنى وزن وتاريخ مرضي ومرفقات</span></div>
        <div>🥗 <strong>مكتبة برامج تغذية</strong><br><span class="muted">علاجية ورياضية جاهزة تُقترح حسب حالة المريض</span></div>
        <div>💉 <strong>حقن ومحاسبة بالوحدات</strong><br><span class="muted">بروتوكول ومخزون وكشف حساب</span></div>
        <div>📅 <strong>مواعيد وتذكير واتساب</strong><br><span class="muted">تأكيد الحضور ورقم الدور</span></div>
        <div>🎟️ <strong>باقات جلسات</strong><br><span class="muted">بيع وخصم ومتابعة المتبقي</span></div>
        <div>📱 <strong>بوابة للمريض</strong><br><span class="muted">يتابع وزنه ونظامه وحسابه من موبايله</span></div>
    </div>

    <?php if ($plans): ?>
    <div class="plans">
        <?php foreach ($plans as $p):
            $per = (float)$p['price'] / max(1, (int)$p['months']);
            $isBest = $best !== null && abs($per - $best) < 0.01 && count($plans) > 1; ?>
            <div class="plan <?= $isBest ? 'best' : '' ?>">
                <?php if ($isBest): ?><span class="tag">الأوفر</span><?php endif; ?>
                <h3><?= e($p['name']) ?></h3>
                <div class="price"><?= e(money($p['price'])) ?></div>
                <div class="per">لكل <?= (int)$p['months'] ?> شهر
                    <?php if ((int)$p['months'] > 1): ?>
                        — أي <?= e(money($per)) ?> شهريًا
                    <?php endif; ?>
                </div>
                <ul>
                    <?php foreach (array_filter(array_map('trim', explode("\n", (string)$p['features']))) as $f): ?>
                        <li><?= e($f) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($open): ?>
                    <a class="btn" href="signup.php?plan=<?= (int)$p['id'] ?>">
                        <?= $trialDays > 0 ? 'ابدأ ' . $trialDays . ' يوم مجانًا' : 'اشترك الآن' ?></a>
                <?php elseif (setting('support_phone') !== ''): ?>
                    <a class="btn" href="tel:<?= e(setting('support_phone')) ?>">اتصل بنا</a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <div class="alert alert-warning">لم تُضَف خطط بعد.</div>
    <?php endif; ?>

    <p class="note">
        <?php if ($trialDays > 0 && $open): ?>
            تجربة <?= $trialDays ?> يومًا مجانًا بدون بطاقة — وبياناتك تبقى ملكك ويمكنك تصديرها في أي وقت.<br>
        <?php endif; ?>
        الدفع بباي موب أو إنستا باي أو كاش.
        <?php if (setting('support_phone') !== ''): ?>
            <br>للاستفسار: <a href="tel:<?= e(setting('support_phone')) ?>" dir="ltr"><?= e(setting('support_phone')) ?></a>
        <?php endif; ?>
    </p>

</div>
</body>
</html>
