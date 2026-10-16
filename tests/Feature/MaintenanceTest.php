<?php

namespace Tests\Feature;

use App\Enums\InstallmentType;
use App\Enums\MaintenanceStatus as S;
use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\MaintenanceRequests\Pages\ListMaintenanceRequests;
use App\Filament\Admin\Resources\MaintenanceRequests\Pages\ViewMaintenanceRequest;
use App\Filament\Client\Resources\MaintenanceRequests\Pages\ManageMaintenanceRequests as ClientMaintenance;
use App\Filament\Owner\Resources\MaintenanceRequests\Pages\ManageMaintenanceRequests as OwnerMaintenance;
use App\Models\Client;
use App\Models\Installment;
use App\Models\MaintenanceRequest;
use App\Models\OutgoingMessage;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\RentContract;
use App\Models\Unit;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Maintenance\MaintenanceService;
use App\Services\Portal\PortalAccounts;
use App\Support\Settings;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Maintenance: the tenant reports, the office books a technician, the owner approves big bills, the cost is charged. */
class MaintenanceTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private Unit $unit;

    private RentContract $contract;

    private Client $renter;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $office = $this->initializeTenancy();
        $this->host = 'http://'.$office->domains()->value('domain');
        // two owners sharing the expenses 70/30
        $this->unit = $this->makeUnit([[60, 70], [40, 30]], ['title' => 'شقة 12 — عمارة النخيل']);
        $this->renter = Client::create(['first_name' => 'سعد', 'family_name' => 'المطيري', 'mobile' => '0551112222', 'email' => 'saad@test.local']);
        $this->contract = app(ContractService::class)->createRent([
            'unit_id' => $this->unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'year', 'rent_amount' => 30000,
        ], [$this->renter->id => 100]);
        $this->admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    private function report(array $data = []): MaintenanceRequest
    {
        return app(MaintenanceService::class)->open($data + [
            'unit_id' => $this->unit->id, 'category' => 'plumbing', 'title' => 'تسريب تحت مغسلة المطبخ',
        ], $this->renter)->fresh();
    }

    private function sent(string $event): \Illuminate\Support\Collection
    {
        return OutgoingMessage::where('event', $event)->get();
    }

    public function test_a_tenant_reports_from_the_portal_and_staff_are_alerted(): void
    {
        $user = app(PortalAccounts::class)->issue($this->renter, 'saad@portal.test', 'secret-pass-1')['user'];
        $this->actingAs($user);
        Filament::setCurrentPanel('client');

        Livewire::test(ClientMaintenance::class)
            ->assertOk()
            ->callAction('create', ['unit_id' => $this->unit->id, 'category' => 'ac', 'priority' => 'urgent', 'title' => 'المكيف لا يبرد', 'preferred_time' => 'بعد العصر'])
            ->assertHasNoActionErrors();

        $request = MaintenanceRequest::firstOrFail();
        $this->assertSame(S::New, $request->status);
        $this->assertSame($this->renter->id, $request->client_id);
        $this->assertSame($this->contract->id, $request->rent_contract_id);
        $this->assertNull($request->created_by);
        $this->assertMatchesRegularExpression('/^MNT-\d{4}-00001$/', $request->code);
        $this->assertSame(1, $this->admin->notifications()->count(), 'the office hears about it in the panel');

        $other = $this->makeUnit([[100, 100]], ['title' => 'وحدة غيره']);
        $this->expectException(BusinessRuleException::class);
        app(MaintenanceService::class)->open(['unit_id' => $other->id, 'title' => 'x'], $this->renter);
    }

    public function test_scheduling_tells_the_tenant_who_is_coming_and_when(): void
    {
        $request = $this->report();

        app(MaintenanceService::class)->schedule($request, ['technician_name' => 'مؤسسة الإتقان', 'technician_phone' => '0559998888',
            'scheduled_at' => '2026-11-02 16:00', 'estimated_cost' => 250]);

        $this->assertSame(S::Scheduled, $request->fresh()->status);
        $sms = $this->sent('maintenance_update')->firstWhere('channel.value', 'sms');
        $this->assertNotNull($sms);
        $this->assertStringContainsString('مؤسسة الإتقان', $sms->body);
        $this->assertStringContainsString('2026-11-02 16:00', $sms->body);
        $this->assertSame(0, $this->sent('maintenance_approval')->count(), 'no approval limit set');
    }

    public function test_estimates_above_the_limit_wait_for_the_owner(): void
    {
        Settings::set('maintenance_approval_limit', 1000, 'general');
        Settings::flush();
        $request = $this->report();
        $this->unit->owners()->get()->each(fn ($o, $i) => $o->update(['mobile' => '050000000'.$i]));

        app(MaintenanceService::class)->schedule($request, ['technician_name' => 'فني', 'scheduled_at' => '2026-11-02 10:00', 'estimated_cost' => 1800]);
        $this->assertSame(S::AwaitingApproval, $request->fresh()->status);
        $this->assertSame(2, $this->sent('maintenance_approval')->count(), 'each owner is asked');
        $this->assertStringContainsString('1,800.00', $this->sent('maintenance_approval')->first()->body);

        $owner = $this->unit->owners()->first();
        $user = app(PortalAccounts::class)->issue($owner, 'owner@portal.test', 'secret-pass-1')['user'];
        $this->actingAs($user);
        Filament::setCurrentPanel('owner');

        Livewire::test(OwnerMaintenance::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$request])
            ->callTableAction('approve', $request, ['note' => 'موافق، ونرجو استخدام قطع أصلية'])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertSame(S::Scheduled, $request->status);
        $this->assertSame('approved', $request->owner_decision);
        $this->assertSame($owner->id, $request->owner_decided_by);
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'وافق المالك')));
    }

    public function test_an_owner_refusal_returns_the_request_to_the_office(): void
    {
        Settings::set('maintenance_approval_limit', 500, 'general');
        Settings::flush();
        $request = $this->report();
        app(MaintenanceService::class)->schedule($request, ['technician_name' => 'فني', 'scheduled_at' => '2026-11-02 10:00', 'estimated_cost' => 900]);

        $this->expectExceptionMessage(__('بانتظار موافقة المالك على التكلفة'));
        try {
            app(MaintenanceService::class)->complete($request->fresh(), ['cost' => 900, 'charge_to' => 'owners']);
        } finally {
            app(MaintenanceService::class)->decide($request->fresh(), $this->unit->owners()->first(), false, 'مرتفعة، اطلبوا عرضًا آخر');
            $this->assertSame(S::New, $request->fresh()->status);
            $this->assertSame('rejected', $request->fresh()->owner_decision);
        }
    }

    public function test_completing_charges_the_owners_by_expenses_share(): void
    {
        $request = $this->report();
        app(MaintenanceService::class)->schedule($request, ['technician_name' => 'مؤسسة الإتقان', 'scheduled_at' => '2026-11-02 10:00']);

        app(MaintenanceService::class)->complete($request->fresh(), ['cost' => 400, 'charge_to' => 'owners', 'resolution' => 'تغيير خلاط ووصلات']);

        $request->refresh();
        $this->assertSame(S::Completed, $request->status);
        $voucher = PaymentVoucher::findOrFail($request->payment_voucher_id);
        $this->assertSame('400.00', (string) $voucher->amount);
        $this->assertSame('مؤسسة الإتقان', $voucher->party_name);
        $this->assertSame($this->unit->id, $voucher->unit_id);
        $charges = OwnerTransaction::where('payment_voucher_id', $voucher->id)->orderByDesc('amount')->pluck('amount')->map(fn ($a) => (string) $a)->all();
        $this->assertSame(['280.00', '120.00'], $charges);
        $this->assertStringContainsString(__('يسعدنا تقييمكم للخدمة من بوابة العملاء.'), $this->sent('maintenance_update')->last()->body);
    }

    public function test_completing_can_bill_the_tenant_or_the_office(): void
    {
        $tenantBill = $this->report(['title' => 'كسر زجاج النافذة']);
        app(MaintenanceService::class)->complete($tenantBill, ['cost' => 350, 'charge_to' => 'contract']);
        $expense = Installment::where('payment_voucher_id', $tenantBill->fresh()->payment_voucher_id)->firstOrFail();
        $this->assertSame(InstallmentType::Expenses, $expense->type);
        $this->assertSame($this->contract->id, $expense->contract_id);
        $this->assertSame('350.00', (string) $expense->amount);

        $officeBill = $this->report(['title' => 'تنظيف خزان']);
        app(MaintenanceService::class)->complete($officeBill, ['cost' => 150, 'charge_to' => 'office']);
        $this->assertSame(0, OwnerTransaction::where('payment_voucher_id', $officeBill->fresh()->payment_voucher_id)->count());
        $this->assertSame(0, Installment::where('payment_voucher_id', $officeBill->fresh()->payment_voucher_id)->count());

        $free = $this->report(['title' => 'شد مسمار']);
        app(MaintenanceService::class)->complete($free, ['cost' => 0]);
        $this->assertNull($free->fresh()->payment_voucher_id);
        $this->assertSame(S::Completed, $free->fresh()->status);
    }

    public function test_the_tenant_rates_once_and_poor_ratings_reach_the_office(): void
    {
        $request = $this->report();
        app(MaintenanceService::class)->complete($request, ['cost' => 0]);
        $user = app(PortalAccounts::class)->issue($this->renter, 'saad@portal.test', 'secret-pass-1')['user'];
        $this->actingAs($user);
        Filament::setCurrentPanel('client');

        Livewire::test(ClientMaintenance::class)
            ->assertTableActionVisible('rate', $request)
            ->callTableAction('rate', $request, ['rating' => 2, 'feedback' => 'تأخر الفني ساعتين'])
            ->assertHasNoTableActionErrors()
            ->assertTableActionHidden('rate', $request);

        $this->assertSame(2, $request->fresh()->rating);
        $this->assertTrue($this->admin->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'تقييم منخفض')));

        $this->expectException(BusinessRuleException::class);
        app(MaintenanceService::class)->rate($request->fresh(), $this->renter, 5);
    }

    public function test_each_portal_sees_only_its_own_requests_and_photos(): void
    {
        $mine = $this->report();
        $otherUnit = $this->makeUnit([[100, 100]], ['title' => 'وحدة أخرى']);
        $other = app(MaintenanceService::class)->open(['unit_id' => $otherUnit->id, 'title' => 'طلب آخر']);
        $photo = $mine->addMediaFromString(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII='))
            ->usingFileName('leak.png')->toMediaCollection('photos');

        $tenantUser = app(PortalAccounts::class)->issue($this->renter, 'saad@portal.test', 'secret-pass-1')['user'];
        $this->actingAs($tenantUser);
        Filament::setCurrentPanel('client');
        Livewire::test(ClientMaintenance::class)->assertCanSeeTableRecords([$mine])->assertCanNotSeeTableRecords([$other]);
        $this->get("{$this->host}/media/{$photo->id}")->assertOk();

        $stranger = app(PortalAccounts::class)->issue($otherUnit->owners()->first(), 'stranger@portal.test', 'secret-pass-1')['user'];
        $this->flushSession();
        $this->actingAs($stranger);
        Filament::setCurrentPanel('owner');
        Livewire::test(OwnerMaintenance::class)->assertCanSeeTableRecords([$other])->assertCanNotSeeTableRecords([$mine]);
        $this->get("{$this->host}/media/{$photo->id}")->assertNotFound();
    }

    public function test_office_screens_run_the_whole_workflow(): void
    {
        $request = $this->report();
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');

        Livewire::test(ListMaintenanceRequests::class)->assertOk()->assertCanSeeTableRecords([$request]);
        Livewire::test(ListMaintenanceRequests::class)
            ->callAction('create', ['unit_id' => $this->unit->id, 'category' => 'electrical', 'priority' => 'emergency', 'title' => 'انقطاع الكهرباء في الصالة'])
            ->assertHasNoActionErrors();
        $this->assertSame($this->admin->id, MaintenanceRequest::latest('id')->first()->created_by);

        Livewire::test(ViewMaintenanceRequest::class, ['record' => $request->id])
            ->assertOk()
            ->assertSee('تسريب تحت مغسلة المطبخ')
            ->callAction('schedule', ['technician_name' => 'مؤسسة الإتقان', 'technician_phone' => '0559998888', 'scheduled_at' => '2026-11-02 16:00', 'estimated_cost' => 300])
            ->assertHasNoActionErrors();
        $this->assertSame(S::Scheduled, $request->fresh()->status);

        Livewire::test(ViewMaintenanceRequest::class, ['record' => $request->id])
            ->callAction('complete', ['cost' => 320, 'charge_to' => 'owners', 'payment_method' => 'cash', 'resolution' => 'تم'])
            ->assertHasNoActionErrors()
            ->assertSee(__('تم الإنجاز'));
        $this->assertNotNull($request->fresh()->payment_voucher_id);

        $this->get("{$this->host}/admin/maintenance-requests")->assertOk();
    }
}
