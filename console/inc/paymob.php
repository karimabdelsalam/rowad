<?php
declare(strict_types=1);

/**
 * تكامل باي موب (Paymob Egypt) — تدفّق Accept الكلاسيكي.
 *
 * ثلاث خطوات قبل تحويل العميل:
 *   1) auth/tokens        → رمز جلسة من مفتاح الـ API
 *   2) ecommerce/orders   → تسجيل طلب بالمبلغ (بالقروش)
 *   3) acceptance/payment_keys → مفتاح دفع مرتبط بالطلب وبقناة التحصيل
 * ثم يُفتح الـ iframe بمفتاح الدفع.
 *
 * التأكيد لا يعتمد أبدًا على عودة العميل للمتصفح، بل على الـ callback الموقّع
 * بـ HMAC-SHA512 القادم من خوادم باي موب (paymob_callback.php).
 *
 * المفاتيح المطلوبة من لوحة تحكم باي موب (Settings → Account Info):
 *   API Key · Integration ID · iFrame ID · HMAC Secret
 */

const PAYMOB_BASE = 'https://accept.paymob.com/api';

function paymob_configured(): bool
{
    foreach (['paymob_api_key', 'paymob_integration_id', 'paymob_iframe_id', 'paymob_hmac'] as $k) {
        if (setting($k) === '') {
            return false;
        }
    }
    return true;
}

/**
 * طلب JSON إلى باي موب.
 *
 * @throws RuntimeException عند فشل الاتصال أو رد غير متوقع
 */
function paymob_post(string $path, array $payload): array
{
    $ch = curl_init(PAYMOB_BASE . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new RuntimeException('تعذر الاتصال ببوابة باي موب: ' . $err);
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new RuntimeException('رد غير مفهوم من باي موب (HTTP ' . $code . ').');
    }
    if ($code >= 400) {
        $msg = $data['detail'] ?? $data['message'] ?? json_encode($data, JSON_UNESCAPED_UNICODE);
        throw new RuntimeException('باي موب رفض الطلب (HTTP ' . $code . '): ' . $msg);
    }
    return $data;
}

/**
 * يجهّز عملية دفع ويعيد رابط الـ iframe ومعرّف الطلب لدى باي موب.
 *
 * @return array{iframe_url:string, order_id:string}
 */
function paymob_start(array $invoice, array $clinic): array
{
    if (!paymob_configured()) {
        throw new RuntimeException('بوابة باي موب غير مضبوطة في الإعدادات.');
    }

    $auth = paymob_post('/auth/tokens', ['api_key' => setting('paymob_api_key')]);
    $token = (string)($auth['token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('لم يصل رمز الدخول من باي موب.');
    }

    // القروش لا القيمة العشرية — باي موب يرفض الكسور
    $cents = (int)round(((float)$invoice['amount'] - (float)$invoice['paid']) * 100);
    if ($cents < 100) {
        throw new RuntimeException('المبلغ المتبقي أقل من الحد الأدنى للدفع الأونلاين.');
    }

    // رقم فريد لكل محاولة: باي موب يرفض تكرار merchant_order_id
    $merchantOrderId = $invoice['number'] . '-' . time();

    $order = paymob_post('/ecommerce/orders', [
        'auth_token'        => $token,
        'delivery_needed'   => false,
        'amount_cents'      => $cents,
        'currency'          => setting('paymob_currency', 'EGP'),
        'merchant_order_id' => $merchantOrderId,
        'items'             => [[
            'name'         => 'اشتراك ' . ($invoice['plan_name'] ?: 'النظام'),
            'amount_cents' => $cents,
            'description'  => 'فاتورة ' . $invoice['number'],
            'quantity'     => 1,
        ]],
    ]);
    $orderId = (string)($order['id'] ?? '');
    if ($orderId === '') {
        throw new RuntimeException('لم يُنشئ باي موب رقم طلب.');
    }

    // باي موب يرفض الحقول الفارغة في بيانات الفوترة، فتُملأ بـ NA
    $na = 'NA';
    $name = trim($clinic['owner_name'] ?: $clinic['name']);
    $parts = preg_split('/\s+/u', $name, 2);
    $phone = preg_replace('/\D/', '', (string)$clinic['phone']) ?: '0000000000';

    $key = paymob_post('/acceptance/payment_keys', [
        'auth_token'     => $token,
        'amount_cents'   => $cents,
        'expiration'     => 3600,
        'order_id'       => $orderId,
        'currency'       => setting('paymob_currency', 'EGP'),
        'integration_id' => (int)setting('paymob_integration_id'),
        'billing_data'   => [
            'first_name'     => $parts[0] ?: $na,
            'last_name'      => $parts[1] ?? $na,
            'email'          => $clinic['email'] ?: 'no-reply@example.com',
            'phone_number'   => $phone,
            'apartment'      => $na, 'floor' => $na, 'street' => $na,
            'building'       => $na, 'shipping_method' => $na,
            'postal_code'    => $na, 'city' => $na, 'country' => 'EG',
            'state'          => $na,
        ],
    ]);
    $payToken = (string)($key['token'] ?? '');
    if ($payToken === '') {
        throw new RuntimeException('لم يصل مفتاح الدفع من باي موب.');
    }

    return [
        'iframe_url' => 'https://accept.paymob.com/api/acceptance/iframes/'
            . rawurlencode(setting('paymob_iframe_id')) . '?payment_token=' . rawurlencode($payToken),
        'order_id'   => $orderId,
    ];
}

/**
 * يتحقق من توقيع الـ callback.
 *
 * باي موب يوقّع حقولًا محدّدة **بترتيب ثابت** بعد تسلسلها نصًا، ثم HMAC-SHA512.
 * أي اختلاف في الترتيب يعطي توقيعًا مختلفًا، فالترتيب هنا مقصود ولا يُعاد ترتيبه.
 */
function paymob_verify_hmac(array $obj, string $received): bool
{
    $secret = setting('paymob_hmac');
    if ($secret === '' || $received === '') {
        return false;
    }

    $get = function (string $path) use ($obj) {
        $cur = $obj;
        foreach (explode('.', $path) as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                return '';
            }
            $cur = $cur[$part];
        }
        if (is_bool($cur)) {
            return $cur ? 'true' : 'false';
        }
        return $cur === null ? '' : (string)$cur;
    };

    $fields = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
        'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
        'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
        'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];
    $concat = '';
    foreach ($fields as $f) {
        $concat .= $get($f);
    }

    return hash_equals(hash_hmac('sha512', $concat, $secret), strtolower($received));
}
