<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * اختبارات منطق الاشتراك — تصف السلوك الذي أثبتناه يدويًا في النظام القديم،
 * فيصبح محفوظًا آليًا بدل أن يعتمد على تذكّر أحد أن يجرّبه.
 */
class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SubscriptionService();
    }

    private function clinic(array $attr = []): Clinic
    {
        return Clinic::create(array_merge([
            'name'   => 'عيادة اختبار',
            'token'  => bin2hex(random_bytes(16)),
            'status' => 'active',
        ], $attr));
    }

    private function invoice(Clinic $c, float $amount, int $months = 12): Invoice
    {
        return Invoice::create([
            'clinic_id'  => $c->id,
            'number'     => $this->svc->nextInvoiceNumber(),
            'issue_date' => now()->toDateString(),
            'amount'     => $amount,
            'months'     => $months,
            'pay_token'  => bin2hex(random_bytes(16)),
        ]);
    }

    private function pay(Invoice $inv, float $amount, string $status = 'confirmed'): Payment
    {
        return Payment::create([
            'invoice_id' => $inv->id,
            'clinic_id'  => $inv->clinic_id,
            'pdate'      => now()->toDateString(),
            'amount'     => $amount,
            'method'     => 'cash',
            'status'     => $status,
        ]);
    }

    public function test_partial_payment_does_not_extend_subscription(): void
    {
        $c = $this->clinic(['expires_at' => now()->addDays(5)->toDateString()]);
        $before = $c->expires_at->toDateString();
        $inv = $this->invoice($c, 4800);

        $this->pay($inv, 2000);
        $inv = $this->svc->recalculate($inv);

        $this->assertSame('partial', $inv->status);
        $this->assertFalse((bool)$inv->applied);
        $this->assertSame($before, $c->fresh()->expires_at->toDateString());
    }

    public function test_pending_payment_is_not_counted(): void
    {
        $c = $this->clinic(['expires_at' => now()->addDays(5)->toDateString()]);
        $inv = $this->invoice($c, 1000);

        $this->pay($inv, 1000, 'pending');
        $inv = $this->svc->recalculate($inv);

        $this->assertSame('unpaid', $inv->status);
        $this->assertEquals(0.0, (float)$inv->paid);
    }

    public function test_full_payment_extends_once_and_only_once(): void
    {
        $c = $this->clinic(['expires_at' => now()->addDays(5)->toDateString()]);
        $expected = $c->expires_at->copy()->addMonths(12)->toDateString();
        $inv = $this->invoice($c, 4800, 12);

        $this->pay($inv, 4800);
        $inv = $this->svc->recalculate($inv);

        $this->assertSame('paid', $inv->status);
        $this->assertTrue((bool)$inv->applied);
        $this->assertSame($expected, $c->fresh()->expires_at->toDateString());

        // إعادة الحساب مرارًا يجب ألا تمدّ الاشتراك ثانيةً
        $this->svc->recalculate($inv);
        $this->svc->recalculate($inv);
        $this->assertSame($expected, $c->fresh()->expires_at->toDateString());
    }

    public function test_unconfirming_a_payment_reverses_the_extension(): void
    {
        $c = $this->clinic(['expires_at' => now()->addDays(5)->toDateString()]);
        $before = $c->expires_at->toDateString();
        $inv = $this->invoice($c, 1000, 3);

        $p = $this->pay($inv, 1000);
        $this->svc->recalculate($inv);
        $this->assertNotSame($before, $c->fresh()->expires_at->toDateString());

        $p->update(['status' => 'pending']);
        $inv = $this->svc->recalculate($inv);

        $this->assertSame('unpaid', $inv->status);
        $this->assertFalse((bool)$inv->applied);
        $this->assertSame($before, $c->fresh()->expires_at->toDateString());
    }

    public function test_renewing_an_expired_subscription_counts_from_today(): void
    {
        // اشتراك انتهى من عشرة أيام: التجديد يبدأ من اليوم لا من التاريخ القديم،
        // فلا يشتري العميل شهرًا مضى بالفعل
        $c = $this->clinic([
            'status'     => 'suspended',
            'expires_at' => now()->subDays(10)->toDateString(),
        ]);
        $inv = $this->invoice($c, 500, 1);

        $this->pay($inv, 500);
        $this->svc->recalculate($inv);

        $c->refresh();
        $this->assertSame(now()->addMonth()->toDateString(), $c->expires_at->toDateString());
        $this->assertSame('active', $c->status, 'السداد يعيد تفعيل العيادة الموقوفة');
    }

    public function test_live_subscription_keeps_its_remaining_days(): void
    {
        // اشتراك باقٍ له 20 يومًا: التجديد يُضاف فوقها لا يبتلعها
        $c = $this->clinic(['expires_at' => now()->addDays(20)->toDateString()]);
        $inv = $this->invoice($c, 500, 1);

        $this->pay($inv, 500);
        $this->svc->recalculate($inv);

        $this->assertSame(
            now()->addDays(20)->addMonth()->toDateString(),
            $c->fresh()->expires_at->toDateString()
        );
    }

    public function test_void_invoice_is_left_untouched(): void
    {
        $c = $this->clinic(['expires_at' => now()->addDays(5)->toDateString()]);
        $before = $c->expires_at->toDateString();
        $inv = $this->invoice($c, 1000, 3);
        $inv->update(['status' => 'void']);

        $this->pay($inv, 1000);
        $this->svc->recalculate($inv);

        $this->assertSame('void', $inv->fresh()->status);
        $this->assertSame($before, $c->fresh()->expires_at->toDateString());
    }

    public function test_overpayment_is_treated_as_paid_without_double_extending(): void
    {
        $c = $this->clinic(['expires_at' => now()->addDays(5)->toDateString()]);
        $expected = $c->expires_at->copy()->addMonths(3)->toDateString();
        $inv = $this->invoice($c, 1000, 3);

        $this->pay($inv, 600);
        $this->pay($inv, 600);   // الإجمالي 1200 لفاتورة 1000
        $inv = $this->svc->recalculate($inv);

        $this->assertSame('paid', $inv->status);
        $this->assertSame($expected, $c->fresh()->expires_at->toDateString());
    }

    public function test_invoice_numbers_increment_within_the_year(): void
    {
        $c = $this->clinic();
        $this->assertSame('INV-' . now()->format('Y') . '-0001', $this->svc->nextInvoiceNumber());

        $this->invoice($c, 100);
        $this->assertSame('INV-' . now()->format('Y') . '-0002', $this->svc->nextInvoiceNumber());
    }

    public function test_mrr_sums_monthly_equivalents_of_active_clinics_only(): void
    {
        $monthly = Plan::create(['name' => 'شهري', 'months' => 1, 'price' => 500]);
        $yearly  = Plan::create(['name' => 'سنوي', 'months' => 12, 'price' => 4800]);

        $this->clinic(['plan_id' => $monthly->id, 'status' => 'active']);
        $this->clinic(['plan_id' => $yearly->id,  'status' => 'active']);
        $this->clinic(['plan_id' => $monthly->id, 'status' => 'suspended']);
        $this->clinic(['plan_id' => $monthly->id, 'status' => 'trial']);

        // 500 (شهري) + 400 (سنوي ÷ 12) — والموقوف والتجريبي لا يُحتسبان
        $this->assertEqualsWithDelta(900.0, $this->svc->monthlyRecurringRevenue(), 0.01);
    }
}
