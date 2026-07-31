<?php
require __DIR__ . '/inc/bootstrap.php';

if (portal_patient()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        exit('انتهت صلاحية الجلسة — أعد المحاولة.');
    }
    $login = trim($_POST['login'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    // الدخول بالكود (P-0001) أو برقم الهاتف
    $st = $pdo->prepare(
        'SELECT * FROM patients WHERE portal_enabled = 1 AND (code = ? OR REPLACE(phone, " ", "") = ?) LIMIT 1'
    );
    $st->execute([$login, str_replace(' ', '', $login)]);
    $row = $st->fetch();

    if ($row && $row['portal_password'] && password_verify($password, $row['portal_password'])) {
        session_regenerate_id(true);
        $_SESSION['patient'] = ['id' => (int)$row['id'], 'name' => $row['name']];
        header('Location: index.php');
        exit;
    }
    sleep(1);
    $error = 'بيانات الدخول غير صحيحة، أو لم تُفعَّل بوابتك بعد. تواصل مع العيادة.';
}
$clinic = setting('clinic_name', 'العيادة');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0f766e">
<title>دخول المرضى | <?= e($clinic) ?></title>
<link rel="stylesheet" href="assets/portal.css">
<link rel="manifest" href="manifest.php">
</head>
<body class="plogin-body">
<div class="plogin">
    <h1>🍏 <?= e($clinic) ?></h1>
    <p class="pmuted">بوابة المرضى — تابع وزنك ونظامك الغذائي ومواعيدك</p>
    <?php if ($error): ?><div class="palert palert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <label>كود المريض أو رقم الهاتف
            <input name="login" required autofocus dir="ltr" placeholder="P-0001 أو 01001234567"></label>
        <label>كلمة المرور <input type="password" name="password" required dir="ltr"></label>
        <button class="pbtn pbtn-block" type="submit">دخول</button>
    </form>
    <p class="pmuted phint">لم تستلم بياناتك؟ اطلبها من استقبال العيادة.</p>
</div>
</body>
</html>
