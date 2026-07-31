<?php
require __DIR__ . '/inc/bootstrap.php';

if (cuser()) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    $st = $pdo->prepare('SELECT * FROM console_users WHERE username = ? AND active = 1');
    $st->execute([$username]);
    $u = $st->fetch();

    if ($u && password_verify($password, $u['password'])) {
        session_regenerate_id(true);
        $_SESSION['cuser'] = ['id' => (int)$u['id'], 'name' => $u['name']];
        log_action($pdo, 'login', 'user', (int)$u['id'], 'دخول: ' . $u['name']);
        redirect('index.php');
    }
    $error = 'اسم المستخدم أو كلمة المرور غير صحيحة.';
    log_action($pdo, 'login_failed', 'user', null, 'محاولة دخول فاشلة: ' . $username);
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
    <h1 class="auth-title">💼 <?= e(setting('brand_name', 'كونسول الاشتراكات')) ?></h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>اسم المستخدم <input name="username" required autofocus dir="ltr"></label>
        <label>كلمة المرور <input type="password" name="password" required dir="ltr"></label>
        <button class="btn btn-block" type="submit">دخول</button>
    </form>
</div>
</body>
</html>
