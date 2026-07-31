<?php

namespace Tests\Feature;

use App\Services\PaymobService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * التحقق من توقيع إشعارات باي موب.
 *
 * هذا هو الحاجز الوحيد بين إشعار حقيقي وإشعار مزوّر يمنح اشتراكًا بلا دفع،
 * فالاختبار هنا ليس رفاهية.
 */
class PaymobServiceTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'TESTHMACSECRET123';

    private PaymobService $paymob;

    protected function setUp(): void
    {
        parent::setUp();
        $settings = new SettingsService();
        $settings->set('paymob_hmac', self::SECRET);
        $this->paymob = new PaymobService($settings);
    }

    /** حدث نموذجي كما ترسله باي موب */
    private function event(array $override = []): array
    {
        return array_replace([
            'id' => 555001, 'amount_cents' => 135000, 'created_at' => '2026-07-31T12:00:00',
            'currency' => 'EGP', 'error_occured' => false, 'has_parent_transaction' => false,
            'integration_id' => 12345, 'is_3d_secure' => true, 'is_auth' => false,
            'is_capture' => false, 'is_refunded' => false, 'is_standalone_payment' => true,
            'is_voided' => false, 'order' => ['id' => 777], 'owner' => 900, 'pending' => false,
            'source_data' => ['pan' => '2346', 'sub_type' => 'MasterCard', 'type' => 'card'],
            'success' => true,
        ], $override);
    }

    /** يحسب التوقيع بنفس ترتيب الحقول الذي تعتمده باي موب */
    private function sign(array $obj, string $secret = self::SECRET): string
    {
        $fields = [
            'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction',
            'id', 'integration_id', 'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded',
            'is_standalone_payment', 'is_voided', 'order.id', 'owner', 'pending',
            'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
        ];
        $concat = '';
        foreach ($fields as $f) {
            $cur = $obj;
            foreach (explode('.', $f) as $part) {
                $cur = $cur[$part];
            }
            $concat .= is_bool($cur) ? ($cur ? 'true' : 'false') : (string)$cur;
        }

        return hash_hmac('sha512', $concat, $secret);
    }

    public function test_accepts_a_correctly_signed_event(): void
    {
        $e = $this->event();
        $this->assertTrue($this->paymob->verifyHmac($e, $this->sign($e)));
    }

    public function test_rejects_a_forged_signature(): void
    {
        $this->assertFalse($this->paymob->verifyHmac($this->event(), str_repeat('de', 64)));
    }

    public function test_rejects_a_signature_made_with_another_secret(): void
    {
        $e = $this->event();
        $this->assertFalse($this->paymob->verifyHmac($e, $this->sign($e, 'SOMEONE_ELSES_KEY')));
    }

    public function test_rejects_when_any_signed_field_was_tampered_with(): void
    {
        $original = $this->event();
        $signature = $this->sign($original);

        // نفس التوقيع مع مبلغ مضاعف يجب أن يُرفض
        $tampered = $this->event(['amount_cents' => 270000]);
        $this->assertFalse($this->paymob->verifyHmac($tampered, $signature));

        // وكذلك تغيير رقم الطلب لتوجيه الدفعة لفاتورة أخرى
        $rerouted = $this->event(['order' => ['id' => 999]]);
        $this->assertFalse($this->paymob->verifyHmac($rerouted, $signature));

        // وقلب حالة النجاح
        $flipped = $this->event(['success' => false]);
        $this->assertFalse($this->paymob->verifyHmac($flipped, $signature));
    }

    public function test_rejects_empty_signature(): void
    {
        $this->assertFalse($this->paymob->verifyHmac($this->event(), ''));
    }

    public function test_signature_is_case_insensitive_in_hex(): void
    {
        $e = $this->event();
        $this->assertTrue($this->paymob->verifyHmac($e, strtoupper($this->sign($e))));
    }

    public function test_reports_unconfigured_when_keys_are_missing(): void
    {
        $this->assertFalse($this->paymob->isConfigured(), 'مفتاح HMAC وحده لا يكفي للتشغيل');
    }
}
