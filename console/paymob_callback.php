<?php
/**
 * استقبال إشعار باي موب بنتيجة العملية.
 *
 * لا يُوثق بأي شيء قادم من المتصفح: التأكيد يتم فقط بعد التحقق من توقيع
 * HMAC-SHA512 القادم من خوادم باي موب، ثم مطابقة رقم الطلب والمبلغ مع الدفعة
 * المعلّقة المسجَّلة عند بدء العملية.
 *
 * يُضبط الرابط في لوحة باي موب:
 *   Transaction Processed Callback → https://.../console/paymob_callback.php
 *   Transaction Response Callback  → https://.../console/paymob_return.php
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/paymob.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);

// باي موب يرسل الحدث في obj، والتوقيع في hmac بالـ query string
$obj = is_array($body) && isset($body['obj']) && is_array($body['obj']) ? $body['obj'] : null;
$hmac = (string)($_GET['hmac'] ?? ($body['hmac'] ?? ''));

if (!$obj) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'payload']));
}

if (!paymob_verify_hmac($obj, $hmac)) {
    // توقيع غير صالح = محاولة انتحال؛ تُسجَّل ولا يُغيَّر شيء
    log_action($pdo, 'paymob_reject', 'payment', null,
        'رفض إشعار باي موب: توقيع غير صالح (order ' . (string)($obj['order']['id'] ?? '?') . ')');
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'hmac']));
}

$orderId = (string)($obj['order']['id'] ?? '');
$txnId   = (string)($obj['id'] ?? '');
$success = !empty($obj['success']) && empty($obj['error_occured']);
$cents   = (int)($obj['amount_cents'] ?? 0);
$refunded = !empty($obj['is_refunded']) || !empty($obj['is_voided']);

$st = $pdo->prepare("SELECT * FROM payments WHERE gateway_order_id = ? AND method = 'paymob' ORDER BY id DESC LIMIT 1");
$st->execute([$orderId]);
$pay = $st->fetch();

if (!$pay) {
    log_action($pdo, 'paymob_orphan', 'payment', null, 'إشعار باي موب لطلب غير معروف: ' . $orderId);
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'unknown_order']));
}

// إشعار مكرر لعملية سبق تأكيدها: لا يُعاد احتساب أي شيء
if ($pay['status'] === 'confirmed' && $success && !$refunded) {
    exit(json_encode(['ok' => true, 'duplicate' => true]));
}

$newStatus = ($success && !$refunded) ? 'confirmed' : 'failed';

// المبلغ يُؤخذ مما أرسلته البوابة فعلًا لا مما طلبه المتصفح
$amount = $cents > 0 ? $cents / 100 : (float)$pay['amount'];

$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE payments SET status = ?, amount = ?, gateway_txn_id = ?, pdate = ? WHERE id = ?')
        ->execute([$newStatus, $amount, $txnId, date('Y-m-d'), (int)$pay['id']]);
    if ($pay['invoice_id']) {
        invoice_recalc($pdo, (int)$pay['invoice_id']);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'db']));
}

log_action($pdo, 'paymob_' . $newStatus, 'payment', (int)$pay['id'],
    'باي موب: ' . ($newStatus === 'confirmed' ? 'نجاح' : 'فشل') . ' — طلب ' . $orderId . ' بمبلغ ' . $amount);

echo json_encode(['ok' => true, 'status' => $newStatus]);
