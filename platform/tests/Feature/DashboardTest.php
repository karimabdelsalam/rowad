<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\ConsoleUser;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): ConsoleUser
    {
        return ConsoleUser::create([
            'name' => 'كريم', 'username' => 'karim', 'password' => 'console1234', 'active' => true,
        ]);
    }

    private function clinic(array $attr = []): Clinic
    {
        return Clinic::create(array_merge([
            'name' => 'عيادة اختبار', 'token' => bin2hex(random_bytes(16)), 'status' => 'active',
        ], $attr));
    }

    public function test_it_renders_the_headline_figures(): void
    {
        $plan = Plan::create(['name' => 'شهري', 'months' => 1, 'price' => 500]);
        $c = $this->clinic(['plan_id' => $plan->id, 'expires_at' => now()->addDays(5)]);

        Payment::create(['clinic_id' => $c->id, 'pdate' => now(), 'amount' => 300,
                         'method' => 'cash', 'status' => 'confirmed']);

        $this->actingAs($this->actor())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('دخل شهري متكرر', false)
            ->assertSee('500.00', false)   // MRR
            ->assertSee('300.00', false);  // تحصيل الشهر
    }

    public function test_overdue_days_are_whole_numbers(): void
    {
        $c = $this->clinic();
        Invoice::create([
            'clinic_id' => $c->id, 'number' => 'INV-X-0001', 'issue_date' => now()->subDays(10),
            'due_date' => now()->subDays(4)->subHours(3),   // كسر يوم متعمَّد
            'amount' => 1000, 'months' => 1, 'pay_token' => bin2hex(random_bytes(16)),
        ]);

        $html = $this->actingAs($this->actor())->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('متأخرة 4 يوم', $html);
        $this->assertDoesNotMatchRegularExpression('/متأخرة \d+\.\d+ يوم/u', $html,
            'أيام التأخر يجب أن تُعرض كعدد صحيح لا ككسر');
    }

    public function test_pending_payments_are_flagged(): void
    {
        $c = $this->clinic();
        Payment::create(['clinic_id' => $c->id, 'pdate' => now(), 'amount' => 500,
                         'method' => 'instapay', 'status' => 'pending']);

        $this->actingAs($this->actor())->get(route('dashboard'))
            ->assertOk()->assertSee('بانتظار تأكيدك', false);
    }

    public function test_pending_payments_are_excluded_from_the_collected_total(): void
    {
        $c = $this->clinic();
        Payment::create(['clinic_id' => $c->id, 'pdate' => now(), 'amount' => 9999,
                         'method' => 'instapay', 'status' => 'pending']);
        Payment::create(['clinic_id' => $c->id, 'pdate' => now(), 'amount' => 100,
                         'method' => 'cash', 'status' => 'confirmed']);

        $html = $this->actingAs($this->actor())->get(route('dashboard'))->assertOk()->getContent();

        // الدفعة المعلّقة تظهر في القائمة بشارتها، لكنها لا تدخل إجمالي التحصيل
        $this->assertStringContainsString('بانتظار التأكيد', $html);
        $this->assertMatchesRegularExpression(
            '/تحصيل هذا الشهر.*?100\.00/su', $html,
            'إجمالي التحصيل يجب أن يحسب المؤكدة وحدها'
        );
    }
}
