<?php
/**
 * تسجيل ذاتي: الطبيب ينشئ عيادته بنفسه ويبدأ فترة تجريبية.
 *
 * صفحة عامة، فتُفعَّل من الإعدادات ولا تعمل إلا إذا فُتح التسجيل عمدًا.
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/provision.php';

$open = setting('signup_open', '0') === '1';
$base = setting('base_domain', '');
$trialDays = max(0, (int)setting('trial_days', '14'));

$errors = [];
$done = null;
$old = fn(string $k) => e($_POST[$k] ?? '');

if ($open && $base !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $in = [
        'clinic_name' => trim($_POST['clinic_name'] ?? ''),
        'owner_name'  => trim($_POST['owner_name'] ?? ''),
        'phone'       => trim($_POST['phone'] ?? ''),
        'email'       => trim($_POST['email'] ?? ''),
        'subdomain'   => strtolower(trim($_POST['subdomain'] ?? '')),
        'admin_name'  => trim($_POST['owner_name'] ?? ''),
        'admin_user'  => strtolower(trim($_POST['admin_user'] ?? '')),
        'admin_pass'  => (string)($_POST['admin_pass'] ?? ''),
    ];

    if ($in['clinic_name'] === '') $errors[] = 'اكتب اسم العيادة.';
    if ($in['owner_name'] === '') $errors[] = 'اكتب اسمك.';
    if ($in['email'] === '' || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'اكتب بريدًا إلكترونيًا صحيحًا.';
    if ($in['phone'] === '') $errors[] = 'اكتب رقم هاتفك.';
    if (!preg_match('/^[a-z0-9._]{3,30}$/', $in['admin_user'])) $errors[] = 'اسم الدخول: 3-30 حرفًا إنجليزيًا صغيرًا أو رقمًا أو نقطة.';
    if (mb_strlen($in['admin_pass']) < 8) $errors[] = 'كلمة المرور 8 أحرف على الأقل.';
    if (($_POST['admin_pass'] ?? '') !== ($_POST['admin_pass2'] ?? '')) $errors[] = 'كلمتا المرور غير متطابقتين.';
    $errors = array_merge($errors, subdomain_errors($pdo, $in['subdomain']));

    // بريد مستخدم بالفعل: يُرفض حتى لا يفتح شخص عيادات بلا حصر بنفس البيانات
    $dup = $pdo->prepare('SELECT id FROM clinics WHERE email = ? AND email <> ""');
    $dup->execute([$in['email']]);
    if ($dup->fetchColumn()) {
        $errors[] = 'يوجد اشتراك بهذا البريد بالفعل — راسلنا لو تحتاج عيادة إضافية.';
    }

    if (!$errors) {
        try {
            $res = create_tenant($pdo, $in);
            log_action($pdo, 'signup', 'clinic', $res['clinic_id'],
                'تسجيل ذاتي: ' . $in['clinic_name'] . ' (' . $in['subdomain'] . ')');
            $done = ['url' => $res['url'], 'user' => $in['admin_user']];
        } catch (Throwable $ex) {
            $errors[] = 'تعذر إنشاء العيادة: ' . $ex->getMessage();
        }
    }
}

$brand = setting('brand_name', 'نظام إدارة العيادات');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ابدأ تجربتك المجانية | <?= e($brand) ?></title>
<link rel="stylesheet" href="assets/console.css">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:620px">

<?php if (!$open || $base === ''): ?>
    <h1 class="auth-title">التسجيل مغلق حاليًا</h1>
    <div class="alert alert-warning">التسجيل الذاتي غير مفتوح في الوقت الحالي.
        <?php if (setting('support_phone') !== ''): ?>
            راسلنا على <span dir="ltr"><?= e(setting('support_phone')) ?></span> لفتح حساب.
        <?php endif; ?>
    </div>

<?php elseif ($done): ?>
    <h1 class="auth-title">🎉 عيادتك جاهزة</h1>
    <div class="alert alert-success">
        تم إنشاء عيادتك وتبدأ تجربتك المجانية <?= $trialDays ?> يومًا من الآن.
    </div>
    <div class="pay-summary">
        <div><span class="muted">رابط عيادتك</span>
            <strong dir="ltr"><a href="<?= e($done['url']) ?>"><?= e($done['url']) ?></a></strong></div>
        <div><span class="muted">اسم الدخول</span><strong dir="ltr"><?= e($done['user']) ?></strong></div>
        <div><span class="muted">كلمة المرور</span><strong>التي اخترتها الآن</strong></div>
    </div>
    <p class="muted">احفظ الرابط. عند أول دخول سيستقبلك معالج يضبط بيانات العيادة وأسعارها في دقيقتين.</p>
    <a class="btn btn-block" href="<?= e($done['url']) ?>">ادخل عيادتك ←</a>

<?php else: ?>
    <h1 class="auth-title">🍏 ابدأ تجربتك المجانية</h1>
    <p class="muted" style="text-align:center">
        <?= $trialDays ?> يومًا مجانًا — بدون بطاقة، وعيادتك جاهزة خلال ثوانٍ.
    </p>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?>
        <h3 class="form-section">العيادة</h3>
        <label>اسم العيادة * <input name="clinic_name" value="<?= $old('clinic_name') ?>" required
            placeholder="عيادة د. منى للتغذية العلاجية"></label>
        <label>العنوان الذي ستدخل منه *
            <input name="subdomain" value="<?= $old('subdomain') ?>" required dir="ltr"
                   pattern="[a-z0-9][a-z0-9-]{1,28}[a-z0-9]" placeholder="mona-clinic">
            <small class="muted">حروف إنجليزية صغيرة وأرقام وشرطات —
                <code dir="ltr">اسمك.<?= e($base) ?></code></small></label>

        <h3 class="form-section">بياناتك</h3>
        <div class="grid2">
            <label>اسمك * <input name="owner_name" value="<?= $old('owner_name') ?>" required placeholder="د. منى عبد الله"></label>
            <label>الهاتف * <input name="phone" value="<?= $old('phone') ?>" required dir="ltr" placeholder="01000000000"></label>
        </div>
        <label>البريد الإلكتروني * <input type="email" name="email" value="<?= $old('email') ?>" required dir="ltr"></label>

        <h3 class="form-section">حساب الدخول</h3>
        <div class="grid2">
            <label>اسم الدخول * <input name="admin_user" value="<?= $old('admin_user') ?>" required dir="ltr" placeholder="mona"></label>
            <label>كلمة المرور * <input type="password" name="admin_pass" required minlength="8" dir="ltr"></label>
        </div>
        <label>تأكيد كلمة المرور * <input type="password" name="admin_pass2" required minlength="8" dir="ltr"></label>

        <button class="btn btn-block" type="submit">أنشئ عيادتي مجانًا</button>
    </form>
<?php endif; ?>

</div>
</body>
</html>
