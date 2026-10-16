<?php

namespace Tests\Feature;

use App\Enums\EndOfServiceReason;
use App\Enums\LeaveStatus;
use App\Enums\PayrollStatus;
use App\Filament\Admin\Resources\EmployeeAdvances\Pages\ManageEmployeeAdvances;
use App\Filament\Admin\Resources\EndOfServiceSettlements\Pages\CreateEndOfServiceSettlement;
use App\Filament\Admin\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Filament\Admin\Resources\PayrollRuns\Pages\ListPayrollRuns;
use App\Filament\Admin\Resources\PayrollRuns\Pages\ViewPayrollRun;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePenalty;
use App\Models\EndOfServiceSettlement;
use App\Models\LeaveRequest;
use App\Models\PaymentVoucher;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Hr\EndOfService;
use App\Services\Hr\Gosi;
use App\Services\Hr\LeaveBalance;
use App\Services\Hr\Payroll;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** HR module: GOSI, leave balances, end-of-service award, payroll runs, WPS file and the screens. */
class HrTest extends TestCase
{
    use InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-15');
        $this->host = 'http://'.$this->initializeTenancy()->domains()->value('domain');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create(['name' => 'مدير', 'email' => 'hr@office.test', 'password' => 'secret-password', 'is_super_admin' => true]);
        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        return $user;
    }

    private function employee(array $attributes = []): Employee
    {
        return Employee::create($attributes + [
            'first_name' => 'سعد', 'family_name' => 'العمري', 'full_name' => 'سعد العمري', 'nationality' => 'سعودي',
            'salary' => 8000, 'housing_allowance' => 2000, 'transport_allowance' => 500, 'start_date' => '2020-01-01', 'iban' => 'SA0380000000608010167519',
        ]);
    }

    public function test_gosi_contributions_follow_nationality_and_the_wage_ceiling(): void
    {
        $saudi = $this->employee();
        $c = Gosi::contributions($saudi);
        $this->assertSame(10000.0, $c['wage']);          // basic + housing
        $this->assertSame(975.0, $c['employee']);        // 9.75 %
        $this->assertSame(1175.0, $c['employer']);       // 11.75 %

        $expat = $this->employee(['nationality' => 'هندي', 'salary' => 50000, 'housing_allowance' => 0]);
        $c = Gosi::contributions($expat);
        $this->assertSame(45000.0, $c['wage']);          // capped
        $this->assertSame(0.0, $c['employee']);
        $this->assertSame(900.0, $c['employer']);        // 2 % hazards only

        $this->assertSame(0.0, Gosi::contributions($this->employee(['gosi_subscribed' => false]))['employer']);
    }

    public function test_annual_leave_entitlement_grows_after_five_years(): void
    {
        $this->assertSame(30, LeaveBalance::entitlement($this->employee()));                          // 6 years in
        $junior = $this->employee(['start_date' => '2025-06-01']);
        $this->assertSame(21, LeaveBalance::entitlement($junior));
        LeaveRequest::create(['employee_id' => $junior->id, 'type' => 'annual', 'start_date' => '2026-02-01', 'end_date' => '2026-02-05', 'status' => 'approved']);
        LeaveRequest::create(['employee_id' => $junior->id, 'type' => 'annual', 'start_date' => '2026-02-10', 'end_date' => '2026-02-12', 'status' => 'pending']);
        $this->assertSame(5, LeaveBalance::used($junior));
        $this->assertSame(16, LeaveBalance::remaining($junior));
    }

    public function test_end_of_service_award_follows_articles_84_85_and_87(): void
    {
        $wage = 10500.0; // 8000 + 2000 + 500
        $e = $this->employee(); // 2020-01-01 → 2026-03-15 ≈ 6.2 years
        $full = EndOfService::calculate($e, CarbonImmutable::parse('2026-03-15'), EndOfServiceReason::Termination);
        $this->assertEqualsWithDelta(6.2, $full['years'], 0.01);
        $this->assertEqualsWithDelta(5 * 0.5 * $wage + ($full['years'] - 5) * $wage, $full['award'], 1);
        $this->assertSame(1.0, $full['share']);

        $resigned = EndOfService::calculate($e, CarbonImmutable::parse('2026-03-15'), EndOfServiceReason::Resignation);
        $this->assertEqualsWithDelta(2 / 3, $resigned['share'], 0.0001); // 5–10 years: two thirds

        $short = $this->employee(['start_date' => '2025-01-01']);
        $this->assertSame(0.0, EndOfService::calculate($short, CarbonImmutable::parse('2026-03-15'), EndOfServiceReason::Resignation)['award']); // under 2 years
        $this->assertGreaterThan(0, EndOfService::calculate($short, CarbonImmutable::parse('2026-03-15'), EndOfServiceReason::MarriageOrBirth)['award']); // art. 87: full
        $this->assertSame(0.0, EndOfService::calculate($e, CarbonImmutable::parse('2026-03-15'), EndOfServiceReason::Article80)['award']);

        $mid = $this->employee(['start_date' => '2023-03-15']); // exactly 3 years → one third
        $c = EndOfService::calculate($mid, CarbonImmutable::parse('2026-03-15'), EndOfServiceReason::Resignation, null, 10);
        $this->assertEqualsWithDelta(3 * 0.5 * $wage / 3, $c['award'], 1);
        $this->assertSame(3500.0, $c['leave_amount']); // 10 days × 10500/30
    }

    public function test_payroll_run_collects_penalties_advances_unpaid_leave_and_gosi_then_pays_with_vouchers(): void
    {
        $this->admin();
        $e = $this->employee();
        $expat = $this->employee(['first_name' => 'Rahul', 'full_name' => 'Rahul Kumar', 'nationality' => 'Indian', 'salary' => 6000, 'housing_allowance' => 0, 'transport_allowance' => 0]);
        $this->employee(['first_name' => 'قديم', 'full_name' => 'قديم منتهي', 'status' => 'terminated']);
        EmployeePenalty::create(['employee_id' => $e->id, 'amount' => 300, 'reason' => 'late', 'for_month' => 3, 'for_year' => 2026, 'issued_at' => '2026-03-02']);
        EmployeeAdvance::create(['employee_id' => $e->id, 'amount' => 3000, 'installment_amount' => 1000, 'remaining' => 3000, 'issued_at' => '2026-02-01']);
        LeaveRequest::create(['employee_id' => $e->id, 'type' => 'unpaid', 'start_date' => '2026-02-27', 'end_date' => '2026-03-03', 'status' => 'approved']); // 3 days in March

        $run = app(Payroll::class)->generate(2026, 3);
        $this->assertCount(2, $run->items); // the terminated employee is left out
        $line = $run->items->firstWhere('employee_id', $e->id);
        $this->assertSame('300.00', $line->penalties);
        $this->assertSame('1000.00', $line->advances);
        $this->assertSame(3, $line->unpaid_leave_days);
        $this->assertSame('1050.00', $line->unpaid_leave);   // 3 × 10500/30
        $this->assertSame('975.00', $line->gosi_employee);
        $this->assertSame('10500.00', $line->gross);
        $this->assertSame('7175.00', $line->net);            // 10500 − 300 − 1000 − 1050 − 975
        $this->assertSame('6000.00', $run->items->firstWhere('employee_id', $expat->id)->net);
        $this->assertSame('13175.00', $run->fresh()->total_net);

        // adjusting a line before approval updates the totals
        $line->forceFill(['bonus' => 500])->save();
        $run->refreshTotals();
        $this->assertSame('13675.00', $run->fresh()->total_net);

        app(Payroll::class)->approve($run);
        $this->assertSame(PayrollStatus::Approved, $run->fresh()->status);
        $paid = app(Payroll::class)->pay($run->fresh(), null, 'transfer');
        $this->assertSame(2, $paid);
        $this->assertSame(2, PaymentVoucher::where('party_type', 'employee')->count());
        $this->assertSame('7675.00', PaymentVoucher::where('party_id', $e->id)->value('amount'));
        $this->assertSame('2000.00', EmployeeAdvance::first()->remaining);
        $this->assertSame(PayrollStatus::Paid, $run->fresh()->status);

        $this->expectException(\App\Exceptions\BusinessRuleException::class); // one run per month
        app(Payroll::class)->generate(2026, 3);
    }

    public function test_wps_file_lists_every_employee_with_iban_and_net(): void
    {
        $this->admin();
        $this->employee();
        $run = app(Payroll::class)->generate(2026, 3);
        $file = app(Payroll::class)->wpsFile($run);
        $lines = array_filter(explode("\r\n", $file));
        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('SIF,', $lines[0]);
        $this->assertStringContainsString('EDR,', $lines[1]);
        $this->assertStringContainsString('SA0380000000608010167519', $lines[1]);
        $this->assertStringContainsString('9525.00', $lines[1]); // 10500 − 975 GOSI
    }

    public function test_hr_screens_render_and_act(): void
    {
        $this->admin();
        $e = $this->employee();

        Livewire::test(ManageLeaveRequests::class)
            ->callAction('create', ['employee_id' => $e->id, 'type' => 'annual', 'start_date' => '2026-04-01', 'end_date' => '2026-04-05', 'reason' => 'سفر'])
            ->assertHasNoActionErrors();
        $leave = LeaveRequest::sole();
        $this->assertSame(5, $leave->days);
        Livewire::test(ManageLeaveRequests::class)->callTableAction('approve', $leave)->assertHasNoTableActionErrors();
        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);

        Livewire::test(ManageEmployeeAdvances::class)
            ->callAction('create', ['employee_id' => $e->id, 'amount' => 2000, 'installment_amount' => 500, 'issued_at' => '2026-03-10'])
            ->assertHasNoActionErrors();
        $this->assertSame('2000.00', EmployeeAdvance::sole()->remaining);

        Livewire::test(ListPayrollRuns::class)->callAction('generate', ['year' => 2026, 'month' => 3])->assertHasNoActionErrors();
        $run = PayrollRun::sole();
        Livewire::test(\App\Filament\Admin\Resources\PayrollRuns\RelationManagers\ItemsRelationManager::class, ['ownerRecord' => $run, 'pageClass' => ViewPayrollRun::class])
            ->assertOk()->assertSee('سعد العمري')->assertSee('9,025.00'); // 10500 − 975 GOSI − 500 advance instalment
        Livewire::test(ViewPayrollRun::class, ['record' => $run->getKey()])->assertOk()->callAction('approve');
        $this->assertSame(PayrollStatus::Approved, $run->fresh()->status);

        $this->get("{$this->host}/print/payslip/{$run->items()->first()->id}")->assertOk()->assertSee('قسيمة راتب')->assertSee('سعد العمري');
        $this->get("{$this->host}/print/payroll/{$run->id}")->assertOk()->assertSee('مسيّر رواتب');

        Livewire::test(CreateEndOfServiceSettlement::class)
            ->fillForm(['employee_id' => $e->id, 'termination_date' => '2026-03-31', 'reason' => 'termination', 'last_wage' => 10500, 'leave_balance_days' => 10, 'other_dues' => 0, 'deductions' => 1500])
            ->call('create')->assertHasNoFormErrors();
        $s = EndOfServiceSettlement::sole();
        $this->assertEqualsWithDelta(6.25, (float) $s->service_years, 0.02);
        $this->assertSame('3500.00', $s->leave_amount);
        $this->assertEqualsWithDelta((float) $s->award + 3500 - 1500, (float) $s->net, 0.01);
        $this->get("{$this->host}/print/end_of_service/{$s->id}")->assertOk()->assertSee('تسوية نهاية خدمة');
    }
}
