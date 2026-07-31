<?php
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/totp.php';

if (cuser()) {
    redirect('index.php');
}

$error = '';
$twoFa = isset($_SESSION['2fa_pending']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $step = $_POST['step'] ?? 'password';

    if ($step === 'password') {
        if ($wait = login_lockout($pdo, 'console_log', 'login_failed')) {
            // القفل يمنع المحاولة نفسها: كلمة المرور الصحيحة لا تُفحص أصلًا أثناءه
            http_response_code(429);
            $error = 'محاولات كثيرة فاشلة — حاول بعد ' . (int)ceil($wait / 60) . ' دقيقة.';
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');

            $st = $pdo->prepare('SELECT * FROM console_users WHERE username = ? AND active = 1');
            $st->execute([$username]);
            $u = $st->fetch();

            if ($u && password_verify($password, $u['password'])) {
                if (($u['totp_secret'] ?? '') !== '') {
                    /*
                     * كلمة المرور صحيحة لكن الحساب عليه تحقق بخطوتين: لا جلسة
                     * بعد — نحفظ الهوية مؤقتًا وننتظر الرمز من تطبيق المصادقة.
                     */
                    $_SESSION['2fa_pending'] = ['id' => (int)$u['id'], 'at' => time()];
                    $twoFa = true;
                } else {
                    session_regenerate_id(true);
                    $_SESSION['cuser'] = ['id' => (int)$u['id'], 'name' => $u['name']];
                    log_action($pdo, 'login', 'user', (int)$u['id'], 'دخول: ' . $u['name']);
                    redirect('index.php');
                }
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
                log_action($pdo, 'login_failed', 'user', null, 'محاولة دخول فاشلة: ' . $username);
                sleep(1);
            }
        }
    }

    if ($step === 'totp') {
        $pending = $_SESSION['2fa_pending'] ?? null;
        // مهلة 5 دقائق لإدخال الرمز، وإلا يعيد كلمة المرور من الأول
        if (!$pending || time() - (int)$pending['at'] > 300) {
            unset($_SESSION['2fa_pending']);
            $twoFa = false;
            $error = 'انتهت المهلة — أعد تسجيل الدخول.';
        } elseif ($wait = login_lockout($pdo, 'console_log', 'totp_failed')) {
            http_response_code(429);
            $error = 'محاولات كثيرة خاطئة — حاول بعد ' . (int)ceil($wait / 60) . ' دقيقة.';
        } else {
            $st = $pdo->prepare('SELECT * FROM console_users WHERE id = ? AND active = 1');
            $st->execute([(int)$pending['id']]);
            $u = $st->fetch();

            if ($u && totp_verify((string)$u['totp_secret'], (string)($_POST['code'] ?? ''))) {
                unset($_SESSION['2fa_pending']);
                session_regenerate_id(true);
                $_SESSION['cuser'] = ['id' => (int)$u['id'], 'name' => $u['name']];
                log_action($pdo, 'login', 'user', (int)$u['id'], 'دخول (بخطوتين): ' . $u['name']);
                redirect('index.php');
            }
            $error = 'الرمز غير صحيح.';
            log_action($pdo, 'totp_failed', 'user', $u ? (int)$u['id'] : null, 'رمز تحقق خاطئ');
            sleep(1);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>دخول الكونسول</title>
<link rel="stylesheet" href="assets/console.css">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1 class="auth-title">💼 <?= e(setting('brand_name', 'Planova بلانوفا')) ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <?php if ($twoFa): ?>
        <p class="muted">افتح تطبيق المصادقة وأدخل الرمز المكوَّن من 6 أرقام.</p>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="step" value="totp">
            <label>رمز التحقق
                <input name="code" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                       autocomplete="one-time-code" autofocus dir="ltr"
                       style="text-align:center;font-size:24px;letter-spacing:8px"></label>
            <button class="btn btn-block" type="submit">تأكيد</button>
        </form>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?><input type="hidden" name="step" value="password">
            <label>اسم المستخدم <input name="username" required autofocus dir="ltr"></label>
            <label>كلمة المرور <input type="password" name="password" required dir="ltr"></label>
            <button class="btn btn-block" type="submit">دخول</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
