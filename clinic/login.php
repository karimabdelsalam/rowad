<?php
require __DIR__ . '/inc/bootstrap.php';

if (user()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? AND active = 1');
    $st->execute([$username]);
    $u = $st->fetch();

    if ($u && password_verify($password, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']];
        activity($pdo, 'login', 'user', (int)$u['id'], 'تسجيل دخول ناجح');
        redirect('index.php');
    }
    activity($pdo, 'login_fail', 'user', null, 'محاولة دخول باسم: ' . mb_substr($username, 0, 60));
    sleep(1); // إبطاء محاولات التخمين
    $error = 'اسم الدخول أو كلمة المرور غير صحيحة.';
}
$clinic = setting('clinic_name', 'عيادة التغذية');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول | <?= e($clinic) ?></title>
<link rel="stylesheet" href="assets/clinic.css">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1 class="auth-title">🍏 <?= e($clinic) ?></h1>
    <p class="muted" style="text-align:center">نظام إدارة عيادة التغذية والتخسيس</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>اسم الدخول <input name="username" required autofocus dir="ltr"></label>
        <label>كلمة المرور <input type="password" name="password" required dir="ltr"></label>
        <button class="btn btn-block" type="submit">دخول</button>
    </form>
</div>
</body>
</html>
