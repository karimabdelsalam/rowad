<?php
/**
 * بحث المرضى للاستخدام في حقول الاختيار السريع.
 *
 * يعيد 20 نتيجة كحد أقصى بصيغة JSON بدل تحميل كل المرضى في الصفحة،
 * حتى تظل الصفحات خفيفة مهما كبر عدد المرضى.
 */
require __DIR__ . '/inc/bootstrap.php';
require_login();

header('Content-Type: application/json; charset=UTF-8');

if (!can('patients.view')) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$q = trim((string)($_GET['q'] ?? ''));
[$df, $args] = doctor_filter('p');

if ($q === '') {
    // بدون بحث: أحدث المرضى المسجّلين كاقتراح أولي
    $st = $pdo->prepare(
        "SELECT p.id, p.code, p.name, p.phone FROM patients p WHERE 1=1 $df ORDER BY p.id DESC LIMIT 10"
    );
    $st->execute($args);
} else {
    $like = '%' . $q . '%';
    $st = $pdo->prepare(
        "SELECT p.id, p.code, p.name, p.phone FROM patients p
         WHERE (p.name LIKE ? OR p.phone LIKE ? OR p.code LIKE ?) $df
         ORDER BY (p.name LIKE ?) DESC, p.name LIMIT 20"
    );
    $st->execute([$like, $like, $like, $q . '%', ...$args]);
}

echo json_encode($st->fetchAll(), JSON_UNESCAPED_UNICODE);
