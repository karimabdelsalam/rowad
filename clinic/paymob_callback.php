<?php
/**
 * إشعار باي موب بنتيجة دفع مريض.
 *
 * لا يُوثق بأي شيء قادم من المتصفح: التأكيد يتم فقط بعد التحقق من توقيع
 * HMAC-SHA512 بمفتاح **هذه العيادة**، ثم مطابقة رقم الطلب مع المطالبة المعلّقة.
 *
 * في وضع SaaS تُحدَّد العيادة من اسم النطاق تلقائيًا، فيصل كل إشعار لقاعدته
 * الصحيحة ويُتحقق منه بمفتاح صاحبه.
 *
 * يُضبط في لوحة باي موب الخاصة بالعيادة:
 *   Transaction Processed Callback → https://<نطاق العيادة>/paymob_callback.php
 *   Transaction Response Callback  → https://<نطاق العيادة>/paymob_return.php
 */
require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/paymob.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
$obj = is_array($body) && isset($body['obj']) && is_array($body['obj']) ? $body['obj'] : null;
$hmac = (string)($_GET['hmac'] ?? ($body['hmac'] ?? ''));

if (!$obj) {
    http_response_code(400);
    exit(json_encode(['ok' => false, 'error' => 'payload']));
}

if (!clinic_paymob_verify($obj, $hmac)) {
    // توقيع غير صالح = محاولة انتحال؛ تُسجَّل ولا يُغيَّر شيء
    activity($pdo, 'pay_reject', 'payreq', null,
        'رفض إشعار دفع: توقيع غير صالح (order ' . (string)($obj['order']['id'] ?? '?') . ')');
    http_response_code(403);
    exit(json_encode(['ok' => false, 'error' => 'hmac']));
}

$orderId  = (string)($obj['order']['id'] ?? '');
$txnId    = (string)($obj['id'] ?? '');
$cents    = (int)($obj['amount_cents'] ?? 0);
$success  = !empty($obj['success']) && empty($obj['error_occured']);
$refunded = !empty($obj['is_refunded']) || !empty($obj['is_voided']);

$st = $pdo->prepare('SELECT * FROM payment_requests WHERE gateway_order_id = ? ORDER BY id DESC LIMIT 1');
$st->execute([$orderId]);
$req = $st->fetch();

if (!$req) {
    activity($pdo, 'pay_orphan', 'payreq', null, 'إشعار دفع لطلب غير معروف: ' . $orderId);
    http_response_code(404);
    exit(json_encode(['ok' => false, 'error' => 'unknown_order']));
}

// إشعار مكرر لعملية سبق تأكيدها: لا تُسجَّل دفعة ثانية
if ($req['status'] === 'paid' && $success && !$refunded) {
    exit(json_encode(['ok' => true, 'duplicate' => true]));
}

if (!$success || $refunded) {
    $pdo->prepare("UPDATE payment_requests SET status = 'failed', gateway_txn_id = ? WHERE id = ?")
        ->execute([$txnId, (int)$req['id']]);
    activity($pdo, 'pay_failed', 'payreq', (int)$req['id'], 'فشل دفع أونلاين — طلب ' . $orderId);
    exit(json_encode(['ok' => true, 'status' => 'failed']));
}

// المبلغ يُؤخذ مما أرسلته البوابة فعلًا لا مما طلبه المتصفح
$amount = $cents > 0 ? $cents / 100 : (float)$req['amount'];

$pdo->beginTransaction();
try {
    $pdo->prepare('INSERT INTO payments (patient_id, pdate, amount, method, service, notes)
                   VALUES (?,?,?,?,?,?)')
        ->execute([
            (int)$req['patient_id'], date('Y-m-d'), $amount, 'card',
            $req['description'] ?: 'تحصيل أونلاين',
            'دفع أونلاين — عملية ' . $txnId,
        ]);
    $payId = (int)$pdo->lastInsertId();

    $pdo->prepare("UPDATE payment_requests SET status = 'paid', method = 'paymob',
                   gateway_txn_id = ?, payment_id = ?, paid_at = NOW() WHERE id = ?")
        ->execute([$txnId, $payId, (int)$req['id']]);
    $pdo->commit();
} catch (Throwable) {
    $pdo->rollBack();
    http_response_code(500);
    exit(json_encode(['ok' => false, 'error' => 'db']));
}

activity($pdo, 'pay_online', 'payreq', (int)$req['id'],
    'تحصيل أونلاين ' . number_format($amount, 2) . ' — عملية ' . $txnId);

echo json_encode(['ok' => true, 'status' => 'paid']);
