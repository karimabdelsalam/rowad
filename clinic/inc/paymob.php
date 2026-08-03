<?php
declare(strict_types=1);

/**
 * تحصيل أونلاين من المرضى عبر باي موب — **بحساب العيادة نفسها**.
 *
 * المفاتيح تُقرأ من إعدادات هذه العيادة، فالفلوس تذهب من المريض إلى حساب
 * العيادة مباشرةً ولا تمر بنا إطلاقًا. هذا مقصود: تحصيل أموال لصالح طرف ثالث
 * ثم تسويتها له نشاط دفع مرخَّص من البنك المركزي، ويخالف شروط مزوّدي الدفع
 * أنفسهم — فلا نضع أنفسنا ولا العيادات في هذا الموضع.
 *
 * التأكيد لا يعتمد أبدًا على عودة المريض للمتصفح، بل على إشعار موقّع
 * بـ HMAC-SHA512 من خوادم باي موب (paymob_callback.php).
 */

const PAYMOB_API = 'https://accept.paymob.com/api';

/** هل ضبطت هذه العيادة بوابتها؟ */
function clinic_paymob_ready(): bool
{
    foreach (['pm_api_key', 'pm_integration_id', 'pm_iframe_id', 'pm_hmac'] as $k) {
        if (setting($k) === '') {
            return false;
        }
    }
    return true;
}

/** @throws RuntimeException */
function pm_post(string $path, array $payload): array
{
    $ch = curl_init(PAYMOB_API . $path);
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
        throw new RuntimeException('تعذر الاتصال ببوابة الدفع: ' . $err);
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) {
        throw new RuntimeException('رد غير مفهوم من بوابة الدفع (HTTP ' . $code . ').');
    }
    if ($code >= 400) {
        $msg = $data['detail'] ?? $data['message'] ?? json_encode($data, JSON_UNESCAPED_UNICODE);
        throw new RuntimeException('البوابة رفضت الطلب (HTTP ' . $code . '): ' . $msg);
    }
    return $data;
}

/**
 * يجهّز عملية دفع لطلب دفع مريض.
 *
 * @return array{iframe_url:string, order_id:string}
 * @throws RuntimeException
 */
function clinic_paymob_start(array $req, array $patient): array
{
    if (!clinic_paymob_ready()) {
        throw new RuntimeException('الدفع الأونلاين غير مضبوط في هذه العيادة.');
    }

    $auth = pm_post('/auth/tokens', ['api_key' => setting('pm_api_key')]);
    $token = (string)($auth['token'] ?? '');
    if ($token === '') {
        throw new RuntimeException('لم يصل رمز الدخول من البوابة.');
    }

    // القروش لا القيمة العشرية — البوابة ترفض الكسور
    $cents = (int)round((float)$req['amount'] * 100);
    if ($cents < 100) {
        throw new RuntimeException('المبلغ أقل من الحد الأدنى للدفع الأونلاين.');
    }

    // رقم فريد لكل محاولة: البوابة ترفض تكرار merchant_order_id
    $merchantOrderId = 'REQ-' . (int)$req['id'] . '-' . time();

    $order = pm_post('/ecommerce/orders', [
        'auth_token'        => $token,
        'delivery_needed'   => false,
        'amount_cents'      => $cents,
        'currency'          => setting('pm_currency', 'EGP'),
        'merchant_order_id' => $merchantOrderId,
        'items'             => [[
            'name'         => mb_substr($req['description'] ?: 'خدمات العيادة', 0, 50),
            'amount_cents' => $cents,
            'description'  => 'دفعة للمريض ' . mb_substr($patient['name'], 0, 40),
            'quantity'     => 1,
        ]],
    ]);
    $orderId = (string)($order['id'] ?? '');
    if ($orderId === '') {
        throw new RuntimeException('لم تُنشئ البوابة رقم طلب.');
    }

    // البوابة ترفض الحقول الفارغة في بيانات الفوترة، فتُملأ بـ NA
    $na = 'NA';
    $parts = preg_split('/\s+/u', trim((string)$patient['name']), 2);
    $phone = preg_replace('/\D/', '', (string)($patient['phone'] ?? '')) ?: '0000000000';

    $key = pm_post('/acceptance/payment_keys', [
        'auth_token'     => $token,
        'amount_cents'   => $cents,
        'expiration'     => 3600,
        'order_id'       => $orderId,
        'currency'       => setting('pm_currency', 'EGP'),
        'integration_id' => (int)setting('pm_integration_id'),
        'billing_data'   => [
            'first_name'  => $parts[0] ?: $na,
            'last_name'   => $parts[1] ?? $na,
            'email'       => 'no-reply@example.com',
            'phone_number' => $phone,
            'apartment'   => $na, 'floor' => $na, 'street' => $na,
            'building'    => $na, 'shipping_method' => $na,
            'postal_code' => $na, 'city' => $na, 'country' => 'EG', 'state' => $na,
        ],
    ]);
    $payToken = (string)($key['token'] ?? '');
    if ($payToken === '') {
        throw new RuntimeException('لم يصل مفتاح الدفع من البوابة.');
    }

    return [
        'iframe_url' => 'https://accept.paymob.com/api/acceptance/iframes/'
            . rawurlencode(setting('pm_iframe_id')) . '?payment_token=' . rawurlencode($payToken),
        'order_id'   => $orderId,
    ];
}

/**
 * يتحقق من توقيع الإشعار بمفتاح HMAC الخاص بهذه العيادة.
 *
 * الحقول تُسلسل **بترتيب ثابت** تحدده باي موب ثم HMAC-SHA512؛ أي اختلاف في
 * الترتيب يعطي توقيعًا مختلفًا، فالترتيب هنا مقصود ولا يُعاد ترتيبه.
 */
function clinic_paymob_verify(array $obj, string $received): bool
{
    $secret = setting('pm_hmac');
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

/* ------------------------------------------------- مديونية المريض */

/**
 * إجمالي ما على المريض: متأخرات الحقن + المتبقي من الباقات.
 *
 * يُحسب من مصادره لا من عمود مخزَّن، فلا يمكن أن يتعارض رقم المديونية مع
 * تفاصيلها بعد تعديل أو حذف.
 */
function patient_due(PDO $pdo, int $patientId): float
{
    $due = 0.0;
    if (module_on('injections')) {
        $st = $pdo->prepare('SELECT COALESCE(SUM(amount - paid), 0) FROM injection_doses WHERE patient_id = ?');
        $st->execute([$patientId]);
        $due += (float)$st->fetchColumn();
    }
    if (module_on('packages')) {
        $st = $pdo->prepare("SELECT COALESCE(SUM(price - paid), 0) FROM patient_packages
                             WHERE patient_id = ? AND status <> 'cancelled'");
        $st->execute([$patientId]);
        $due += (float)$st->fetchColumn();
    }
    return round(max(0, $due), 2);
}

/** رابط الدفع العلني لطلب دفع */
function payreq_url(string $token): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    $dir = rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
    // صفحات البوابة تقع في مجلد فرعي، فنرجع خطوة للجذر
    $dir = preg_replace('#/portal$#', '', $dir) ?? $dir;
    return $host !== '' ? $scheme . '://' . $host . $dir . '/paylink.php?t=' . $token : '';
}
