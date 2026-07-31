<?php
require __DIR__ . '/inc/bootstrap.php';
if (user()) {
    activity($pdo, 'logout', 'user', (int)user()['id'], 'تسجيل خروج');
}
$_SESSION = [];
session_destroy();
redirect('login.php');
