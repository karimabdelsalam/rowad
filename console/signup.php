<?php
/**
 * تسجيل ذاتي: الطبيب ينشئ عيادته بنفسه ويبدأ فترة تجريبية.
 *
 * صفحة عامة، فتُفعَّل من الإعدادات ولا تعمل إلا إذا فُتح التسجيل عمدًا.
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/provision.php';
require_once __DIR__ . '/inc/whatsapp.php';

$open = setting('signup_open', '0') === '1';
$base = setting('base_domain', '');
$trialDays = max(0, (int)setting('trial_days', '14'));
$otpOn = wa_enabled();

$errors = [];
$done = null;
$otpStep = false;      // نعرض شاشة إدخال الرمز؟
$old = fn(string $k) => e($_POST[$k] ?? '');

$plans = $pdo->query('SELECT id, name, months, price FROM plans WHERE active = 1 ORDER BY months')->fetchAll();
$pickedPlan = (int)($_POST['plan_id'] ?? $_GET['plan'] ?? 0);

/** يتحقق من مدخلات التسجيل ويعيد قائمة الأخطاء */
$validate = function (array $in) use ($pdo): array {
    $errors = [];
    if ($in['clinic_name'] === '') $errors[] = 'اكتب اسم العيادة.';
    if ($in['owner_name'] === '') $errors[] = 'اكتب اسمك.';
    if ($in['email'] === '' || !filter_var($in['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'اكتب بريدًا إلكترونيًا صحيحًا.';
    if (!wa_normalize_phone($in['phone'])) $errors[] = 'اكتب رقم هاتف صحيحًا.';
    if (!preg_match('/^[a-z0-9._]{3,30}$/', $in['admin_user'])) $errors[] = 'اسم الدخول: 3-30 حرفًا إنجليزيًا صغيرًا أو رقمًا أو نقطة.';
    if (mb_strlen($in['admin_pass']) < 8) $errors[] = 'كلمة المرور 8 أحرف على الأقل.';
    $errors = array_merge($errors, subdomain_errors($pdo, $in['subdomain']));

    // بريد مستخدم بالفعل: يُرفض حتى لا يفتح شخص عيادات بلا حصر بنفس البيانات
    $dup = $pdo->prepare('SELECT id FROM clinics WHERE email = ? AND email <> ""');
    $dup->execute([$in['email']]);
    if ($dup->fetchColumn()) {
        $errors[] = 'يوجد اشتراك بهذا البريد بالفعل — راسلنا لو تحتاج عيادة إضافية.';
    }

    /*
     * كل تسجيل يُنشئ قاعدة بيانات كاملة، فبدون حدٍّ يستطيع زائر واحد أن يملأ
     * السيرفر بقواعد وهمية. الحد على مستوى الـ IP وعلى مستوى اليوم كله.
     */
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $perIp = max(1, (int)setting('signup_max_per_ip', '3'));
    $perDay = max(1, (int)setting('signup_max_per_day', '50'));

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM console_log
                          WHERE action = 'signup' AND ip = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    $cnt->execute([$ip]);
    if ((int)$cnt->fetchColumn() >= $perIp) {
        $errors[] = 'تجاوزت عدد التسجيلات المسموح بها اليوم. راسلنا لفتح عيادة إضافية.';
    }

    $cntAll = $pdo->query("SELECT COUNT(*) FROM console_log
                           WHERE action = 'signup' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
    if ((int)$cntAll->fetchColumn() >= $perDay) {
        $errors[] = 'التسجيل متوقف مؤقتًا لضغط الطلبات. برجاء المحاولة لاحقًا أو مراسلتنا.';
    }
    return $errors;
};

/** ينشئ العيادة بعد اكتمال كل التحقق */
$finish = function (array $in) use ($pdo, &$done, &$errors): void {
    try {
        $res = create_tenant($pdo, $in);
        log_action($pdo, 'signup', 'clinic', $res['clinic_id'],
            'تسجيل ذاتي: ' . $in['clinic_name'] . ' (' . $in['subdomain'] . ')');
        $done = ['url' => $res['url'], 'user' => $in['admin_user']];
        unset($_SESSION['signup_pending']);
    } catch (Throwable $ex) {
        $errors[] = 'تعذر إنشاء العيادة: ' . $ex->getMessage();
    }
};

if ($open && $base !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $step = $_POST['step'] ?? 'form';

    if ($step === 'form') {
        $in = [
            'clinic_name' => trim($_POST['clinic_name'] ?? ''),
            'owner_name'  => trim($_POST['owner_name'] ?? ''),
            'phone'       => trim($_POST['phone'] ?? ''),
            'email'       => trim($_POST['email'] ?? ''),
            'subdomain'   => strtolower(trim($_POST['subdomain'] ?? '')),
            'admin_name'  => trim($_POST['owner_name'] ?? ''),
            'admin_user'  => strtolower(trim($_POST['admin_user'] ?? '')),
            'admin_pass'  => (string)($_POST['admin_pass'] ?? ''),
            'plan_id'     => (int)($_POST['plan_id'] ?? 0),
        ];
        if (($_POST['admin_pass'] ?? '') !== ($_POST['admin_pass2'] ?? '')) {
            $errors[] = 'كلمتا المرور غير متطابقتين.';
        }

        // الخطة تُقبل فقط إن كانت مفعّلة، فلا تُمرَّر خطة موقوفة أو غير موجودة
        if ($in['plan_id']) {
            $chk = $pdo->prepare('SELECT id FROM plans WHERE id = ? AND active = 1');
            $chk->execute([$in['plan_id']]);
            if (!$chk->fetchColumn()) {
                $in['plan_id'] = 0;
            }
        }
        $errors = array_merge($errors, $validate($in));

        if (!$errors) {
            if ($otpOn) {
                /*
                 * تأكيد رقم الواتساب قبل الإنشاء: البيانات تُحفظ في الجلسة
                 * (على السيرفر) ويُرسل رمز للرقم، فلا تُنشأ عيادة برقم لا
                 * يملكه صاحبه.
                 */
                $sent = otp_send($in['phone']);
                if ($sent['ok']) {
                    $_SESSION['signup_pending'] = $in;
                    $otpStep = true;
                } else {
                    $errors[] = 'تعذر إرسال رمز التحقق: ' . $sent['error'];
                }
            } else {
                $finish($in);
            }
        }
    }

    if ($step === 'otp') {
        $in = $_SESSION['signup_pending'] ?? null;
        if (!$in) {
            $errors[] = 'انتهت الجلسة — أعد ملء النموذج.';
        } elseif (isset($_POST['resend'])) {
            $sent = otp_send($in['phone']);
            $errors[] = $sent['ok'] ? '' : $sent['error'];
            $errors = array_filter($errors);
            if ($sent['ok']) {
                flash('أُعيد إرسال الرمز.');
            }
            $otpStep = true;
        } else {
            $ver = otp_verify((string)($_POST['code'] ?? ''), $in['phone']);
            if (!$ver['ok']) {
                $errors[] = $ver['error'];
                // الجلسة لا تزال تحمل البيانات؛ نبقى في شاشة الرمز إلا إذا أُلغي الرمز
                $otpStep = isset($_SESSION['otp']);
            } else {
                // إعادة التحقق النهائي: قد يكون النطاق حُجز أثناء انتظار الرمز
                $errors = $validate($in);
                if (!$errors) {
                    $finish($in);
                } else {
                    unset($_SESSION['signup_pending']);
                }
            }
        }
    }
}

$brand = PRODUCT_NAME;
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

<?php elseif ($otpStep): ?>
    <h1 class="auth-title">📱 أكّد رقم الواتساب</h1>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>
    <?php foreach ($_SESSION['flash'] ?? [] as $f): ?>
        <div class="alert alert-<?= e($f['t']) ?>"><?= e($f['m']) ?></div>
    <?php endforeach; unset($_SESSION['flash']); ?>
    <p class="muted">أرسلنا رمزًا من 6 أرقام على واتساب رقم
        <strong dir="ltr"><?= e($_SESSION['signup_pending']['phone'] ?? '') ?></strong>.
        أدخله لإتمام إنشاء عيادتك.</p>
    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="step" value="otp">
        <label>رمز التحقق
            <input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                   autocomplete="one-time-code" dir="ltr"
                   style="text-align:center;font-size:24px;letter-spacing:8px"></label>
        <button class="btn btn-block" type="submit">تأكيد وإنشاء العيادة</button>
    </form>
    <form method="post" style="margin-top:10px">
        <?= csrf_field() ?><input type="hidden" name="step" value="otp">
        <button class="btn btn-light btn-block" type="submit" name="resend" value="1">إعادة إرسال الرمز</button>
    </form>
    <p class="muted" style="margin-top:10px;text-align:center">
        <a href="signup.php">رقم غلط؟ ارجع للنموذج</a></p>

<?php else: ?>
    <h1 class="auth-title">🍏 ابدأ تجربتك المجانية</h1>
    <p class="muted" style="text-align:center">
        <?= $trialDays ?> يومًا مجانًا — بدون بطاقة، وعيادتك جاهزة خلال ثوانٍ.
    </p>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post">
        <?= csrf_field() ?><input type="hidden" name="step" value="form">
        <?php if ($plans): ?>
        <h3 class="form-section">الخطة بعد التجربة</h3>
        <label>تبدأ بالتجربة المجانية، ولا يُطلب الدفع قبل انتهائها
            <select name="plan_id">
                <option value="">أقرر بعدين</option>
                <?php foreach ($plans as $p): ?>
                    <option value="<?= (int)$p['id'] ?>" <?= $pickedPlan === (int)$p['id'] ? 'selected' : '' ?>>
                        <?= e($p['name']) ?> — <?= e(money($p['price'])) ?> / <?= (int)$p['months'] ?> شهر</option>
                <?php endforeach; ?>
            </select></label>
        <?php endif; ?>

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
            <label>رقم الواتساب *
                <input name="phone" value="<?= $old('phone') ?>" required dir="ltr" placeholder="01000000000">
                <?php if ($otpOn): ?><small class="muted">سيصلك رمز تحقق عليه</small><?php endif; ?></label>
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
