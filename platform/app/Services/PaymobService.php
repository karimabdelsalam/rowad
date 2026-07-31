<?php

namespace App\Services;

use App\Models\Clinic;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * تكامل باي موب — تدفّق Accept الكلاسيكي.
 *
 * التأكيد لا يعتمد أبدًا على عودة العميل للمتصفح، بل على إشعار موقّع
 * بـ HMAC-SHA512 من خوادم باي موب.
 */
class PaymobService
{
    private const BASE = 'https://accept.paymob.com/api';

    public function __construct(private SettingsService $settings)
    {
    }

    public function isConfigured(): bool
    {
        foreach (['paymob_api_key', 'paymob_integration_id', 'paymob_iframe_id', 'paymob_hmac'] as $k) {
            if ($this->settings->get($k) === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * يجهّز عملية دفع ويعيد رابط الـ iframe ورقم الطلب لدى باي موب.
     *
     * @return array{iframe_url: string, order_id: string}
     */
    public function startPayment(Invoice $invoice, Clinic $clinic): array
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('بوابة باي موب غير مضبوطة في الإعدادات.');
        }

        $token = $this->post('/auth/tokens', ['api_key' => $this->settings->get('paymob_api_key')])['token'] ?? '';
        if ($token === '') {
            throw new RuntimeException('لم يصل رمز الدخول من باي موب.');
        }

        // القروش لا القيمة العشرية — باي موب يرفض الكسور
        $cents = (int)round($invoice->remaining() * 100);
        if ($cents < 100) {
            throw new RuntimeException('المبلغ المتبقي أقل من الحد الأدنى للدفع الأونلاين.');
        }

        $order = $this->post('/ecommerce/orders', [
            'auth_token'      => $token,
            'delivery_needed' => false,
            'amount_cents'    => $cents,
            'currency'        => $this->settings->get('paymob_currency', 'EGP'),
            // رقم فريد لكل محاولة: باي موب يرفض تكرار merchant_order_id
            'merchant_order_id' => $invoice->number . '-' . time(),
            'items' => [[
                'name'         => 'اشتراك ' . ($invoice->plan_name ?: 'النظام'),
                'amount_cents' => $cents,
                'description'  => 'فاتورة ' . $invoice->number,
                'quantity'     => 1,
            ]],
        ]);
        $orderId = (string)($order['id'] ?? '');
        if ($orderId === '') {
            throw new RuntimeException('لم يُنشئ باي موب رقم طلب.');
        }

        $key = $this->post('/acceptance/payment_keys', [
            'auth_token'     => $token,
            'amount_cents'   => $cents,
            'expiration'     => 3600,
            'order_id'       => $orderId,
            'currency'       => $this->settings->get('paymob_currency', 'EGP'),
            'integration_id' => (int)$this->settings->get('paymob_integration_id'),
            'billing_data'   => $this->billingData($clinic),
        ]);
        $payToken = (string)($key['token'] ?? '');
        if ($payToken === '') {
            throw new RuntimeException('لم يصل مفتاح الدفع من باي موب.');
        }

        return [
            'iframe_url' => self::BASE . '/acceptance/iframes/'
                . rawurlencode($this->settings->get('paymob_iframe_id'))
                . '?payment_token=' . rawurlencode($payToken),
            'order_id' => $orderId,
        ];
    }

    /**
     * يتحقق من توقيع الـ callback.
     *
     * باي موب يوقّع حقولًا محدّدة **بترتيب ثابت** بعد تسلسلها نصًا ثم
     * HMAC-SHA512. أي اختلاف في الترتيب يعطي توقيعًا مختلفًا، فالترتيب هنا
     * مقصود ولا يُعاد ترتيبه أبدًا.
     */
    public function verifyHmac(array $obj, string $received): bool
    {
        $secret = $this->settings->get('paymob_hmac');
        if ($secret === '' || $received === '') {
            return false;
        }

        $fields = [
            'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
            'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
            'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
            'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
        ];

        $concat = '';
        foreach ($fields as $f) {
            $concat .= $this->flatten($obj, $f);
        }

        return hash_equals(hash_hmac('sha512', $concat, $secret), strtolower($received));
    }

    /** قيمة حقل متداخل كنص بالصيغة التي يوقّعها باي موب */
    private function flatten(array $obj, string $path): string
    {
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
    }

    /** باي موب يرفض الحقول الفارغة في بيانات الفوترة، فتُملأ بـ NA */
    private function billingData(Clinic $clinic): array
    {
        $na = 'NA';
        $parts = preg_split('/\s+/u', trim($clinic->owner_name ?: $clinic->name), 2);
        $phone = preg_replace('/\D/', '', (string)$clinic->phone) ?: '0000000000';

        return [
            'first_name'   => $parts[0] ?: $na,
            'last_name'    => $parts[1] ?? $na,
            'email'        => $clinic->email ?: 'no-reply@example.com',
            'phone_number' => $phone,
            'apartment'    => $na, 'floor' => $na, 'street' => $na,
            'building'     => $na, 'shipping_method' => $na,
            'postal_code'  => $na, 'city' => $na, 'country' => 'EG', 'state' => $na,
        ];
    }

    private function post(string $path, array $payload): array
    {
        $res = Http::timeout(30)->acceptJson()->post(self::BASE . $path, $payload);

        if ($res->failed()) {
            $body = $res->json();
            $msg = $body['detail'] ?? $body['message'] ?? $res->body();
            throw new RuntimeException('باي موب رفض الطلب (HTTP ' . $res->status() . '): ' . $msg);
        }

        return $res->json() ?? [];
    }
}
