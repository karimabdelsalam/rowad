<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Installment;
use App\Models\Plan;
use App\Models\ReceiptVoucher;
use App\Models\RentContract;
use App\Models\Unit;
use App\Models\User;
use App\Services\Finance\VoucherService;
use App\Services\Installments\InstallmentScheduler;
use App\Support\Branches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Multi-branch offices: staff tied to a branch see and create only within it; admins see everything. */
class BranchesTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private Branch $riyadh;

    private Branch $jeddah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(); // central plans
        $tenant = $this->initializeTenancy(['plan_id' => Plan::where('slug', 'enterprise')->value('id')]);
        $this->host = 'http://'.$tenant->domains()->value('domain');
        $this->riyadh = Branch::create(['name' => 'فرع الرياض']);
        $this->jeddah = Branch::create(['name' => 'فرع جدة']);
        Branches::flush();
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function staff(string $email, ?Branch $branch, string $role = 'agent'): User
    {
        $user = User::create(['type' => 'staff', 'name' => $email, 'email' => $email, 'password' => 'secret-password', 'branch_id' => $branch?->id]);
        $user->assignRole($role);

        return $user;
    }

    private function portfolio(Branch $branch, string $title): RentContract
    {
        $contract = $this->makeRentContract([], $this->makeUnit([[100, 100]], ['title' => $title, 'branch_id' => $branch->id]));
        app(InstallmentScheduler::class)->schedule($contract);

        return $contract;
    }

    public function test_branch_staff_see_only_their_branch(): void
    {
        $ruh = $this->portfolio($this->riyadh, 'شقة الرياض');
        $jed = $this->portfolio($this->jeddah, 'شقة جدة');

        $agent = $this->staff('agent@ruh.test', $this->riyadh);
        $this->actingAs($agent);
        $this->assertSame(['شقة الرياض'], Unit::pluck('title')->all());
        $this->assertSame([$ruh->id], RentContract::pluck('id')->all());
        $this->assertSame(0, Installment::whereHasMorph('contract', [RentContract::class], fn ($q) => $q->whereKey($jed->id))->count());
        $this->assertGreaterThan(0, Installment::count());

        $this->get("{$this->host}/admin/units")->assertOk()->assertSee('شقة الرياض')->assertDontSee('شقة جدة');
        $this->get("{$this->host}/admin/units/{$jed->unit_id}")->assertNotFound();
        $this->get("{$this->host}/admin/rent-contracts/{$jed->id}")->assertNotFound();

        // what they create lands in their branch
        $unit = Unit::create(['title' => 'وحدة جديدة', 'offer_type' => 'rent']);
        $this->assertSame($this->riyadh->id, $unit->branch_id);

        // an admin without a branch sees both branches and can filter
        $admin = $this->staff('admin@office.test', null, 'admin');
        $this->flushSession(); // a new person at the keyboard
        $this->actingAs($admin);
        $this->assertSame(3, Unit::count());
        $this->get("{$this->host}/admin/units")->assertOk()->assertSee('شقة الرياض')->assertSee('شقة جدة')->assertSee('فرع جدة');
        $this->get("{$this->host}/admin/branches")->assertOk()->assertSee('فرع الرياض');

        // branch staff never manage branches
        $this->flushSession();
        $this->actingAs($agent);
        $this->get("{$this->host}/admin/branches")->assertForbidden();
    }

    public function test_vouchers_follow_their_unit_branch_even_without_a_user(): void
    {
        $jed = $this->portfolio($this->jeddah, 'شقة جدة');
        $installment = $jed->installments()->orderBy('due_date')->first();

        // e.g. the payment webhook: no signed-in user
        $voucher = app(VoucherService::class)->createReceipt(['issued_at' => today(), 'amount' => $installment->amount, 'payment_method' => 'card', 'unit_id' => $jed->unit_id], [$installment->id]);
        $this->assertSame($this->jeddah->id, $voucher->branch_id);

        $this->actingAs($this->staff('acc@ruh.test', $this->riyadh, 'accountant'));
        $this->assertSame(0, ReceiptVoucher::count());
        $this->actingAs($this->staff('acc@jed.test', $this->jeddah, 'accountant'));
        $this->assertSame(1, ReceiptVoucher::count());
    }

    public function test_offices_without_the_feature_are_untouched(): void
    {
        tenant()->update(['plan_id' => Plan::where('slug', 'basic')->value('id')]);
        $this->portfolio($this->riyadh, 'شقة الرياض');
        $this->portfolio($this->jeddah, 'شقة جدة');
        $this->actingAs($this->staff('agent@ruh.test', $this->riyadh));
        $this->assertFalse(Branches::enabled());
        $this->assertSame(2, Unit::count());
        $this->get("{$this->host}/admin/branches")->assertForbidden();
    }
}
