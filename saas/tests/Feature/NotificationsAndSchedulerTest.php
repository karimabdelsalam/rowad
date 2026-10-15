<?php

namespace Tests\Feature;

use App\Enums\NotificationEvent;
use App\Models\Employee;
use App\Models\GovernmentLicense;
use App\Models\Installment;
use App\Models\OutgoingMessage;
use App\Models\OwnerTransaction;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Scheduling\DailyOfficeJobs;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

class NotificationsAndSchedulerTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private int $smsStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-01-10');
        $this->initializeTenancy();
        Http::fake(['api.unifonic.com/*' => fn () => $this->smsStatus === 200
            ? Http::response(['data' => ['MessageID' => 1]])
            : Http::response(['message' => 'bad'], $this->smsStatus)]);
        Settings::set('sms_provider', 'unifonic');
        Settings::set('sms_username', 'APP-SID');
        Settings::set('sms_password', 'x');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function rentWithContacts(array $overrides = []): \App\Models\RentContract
    {
        $unit = $this->makeUnit();
        $unit->owners()->first()->update(['email' => 'owner@example.com', 'mobile' => '0500000001']);
        $client = $this->makeClient();
        $client->update(['email' => 'renter@example.com', 'mobile' => '0500000002']);

        return app(ContractService::class)->createRent($overrides + [
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month', 'rent_amount' => 2500,
        ], [$client->id => 100]);
    }

    public function test_new_rent_contract_notifies_renter_and_owner_by_email_and_sms(): void
    {
        $contract = $this->rentWithContacts();

        $renterMail = OutgoingMessage::where('event', NotificationEvent::NewRentContract->value)->where('channel', 'mail')->sole();
        $this->assertSame('renter@example.com', $renterMail->recipient);
        $this->assertStringContainsString($contract->number, $renterMail->body);
        $this->assertStringContainsString('شقة 101', $renterMail->body);
        $this->assertSame('sent', $renterMail->fresh()->status); // sync queue in tests

        $ownerSms = OutgoingMessage::where('event', NotificationEvent::OwnerUnitRented->value)->where('channel', 'sms')->sole();
        $this->assertSame('0500000001', $ownerSms->recipient);
        $this->assertSame('sent', $ownerSms->fresh()->status);
        Http::assertSent(fn ($r) => $r['Recipient'] === '966500000001');
    }

    public function test_disabled_template_channel_sends_nothing(): void
    {
        \App\Models\MessageTemplate::where('event', NotificationEvent::NewRentContract->value)->update(['email_enabled' => false, 'sms_enabled' => false]);
        $this->rentWithContacts();

        $this->assertSame(0, OutgoingMessage::where('event', NotificationEvent::NewRentContract->value)->count());
    }

    public function test_failed_sms_is_logged_with_the_provider_error(): void
    {
        $this->smsStatus = 401;
        $this->rentWithContacts();

        $sms = OutgoingMessage::where('channel', 'sms')->first();
        $this->assertSame(['failed', 'HTTP 401'], [$sms->status, $sms->error]);
    }

    public function test_rent_reminder_is_sent_once_fifteen_days_before(): void
    {
        $this->rentWithContacts();
        OutgoingMessage::query()->delete();
        $jobs = app(DailyOfficeJobs::class);

        $this->assertSame(0, $jobs->installmentReminders()); // Jan 10: nothing due in the next 15 days

        Date::setTestNow('2026-01-17');                     // Feb 1 is now 15 days away
        $this->assertSame(1, $jobs->installmentReminders());
        $this->assertSame(['2026-02-01'], Installment::where('reminder_stage', 15)->get()->map(fn ($i) => $i->due_date->toDateString())->all());
        $this->assertSame(0, $jobs->installmentReminders()); // never repeated
        $this->assertSame(['rent_due_15', 'rent_due_15'], OutgoingMessage::orderBy('id')->pluck('event')->all()); // email + SMS
    }

    public function test_sale_reminder_stages_and_daily_overdue(): void
    {
        $unit = $this->makeUnit([[100, 100]], ['offer_type' => 'sale']);
        $client = $this->makeClient();
        $client->update(['mobile' => '0500000009']);
        $contract = app(ContractService::class)->createSale(['unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01',
            'total_amount' => 30000, 'payment_type' => 'installment', 'payment_plan' => ['first_payment' => 0, 'count' => 1, 'cycle' => 'month', 'start_date' => '2026-01-20']], [$client->id => 100]);
        $installment = $contract->installments()->sole(); // due 2026-02-20
        $jobs = app(DailyOfficeJobs::class);
        $events = fn () => OutgoingMessage::orderBy('id')->pluck('event')->all();

        Date::setTestNow('2026-02-06'); $jobs->installmentReminders();
        Date::setTestNow('2026-02-19'); $jobs->installmentReminders();
        Date::setTestNow('2026-02-20'); $jobs->installmentReminders();
        Date::setTestNow('2026-02-21'); $jobs->installmentReminders(); $jobs->installmentReminders();
        Date::setTestNow('2026-02-22'); $jobs->installmentReminders();

        $this->assertSame(['sale_due_15', 'sale_due_1', 'sale_due_0', 'sale_overdue', 'sale_overdue'], $events());
    }

    public function test_employee_and_licence_expiry_alert_office_and_staff_once(): void
    {
        Settings::set('email', 'office@example.com');
        $admin = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $admin->assignRole('admin');
        Employee::create(['first_name' => 'جون', 'family_name' => 'دو', 'id_number' => '2400000000', 'id_expiry_date' => '2026-01-20']);
        GovernmentLicense::create(['title' => 'رخصة فال', 'expiry_date' => '2026-01-15']);
        GovernmentLicense::create(['title' => 'رخصة بعيدة', 'expiry_date' => '2026-06-15']);
        $jobs = app(DailyOfficeJobs::class);

        $this->assertSame([1, 1], [$jobs->employeeIdExpiry(), $jobs->licenseExpiry()]);
        $this->assertSame([0, 0], [$jobs->employeeIdExpiry(), $jobs->licenseExpiry()]);
        $this->assertSame(2, $admin->notifications()->count());
        $this->assertStringContainsString('رخصة فال', OutgoingMessage::where('event', 'license_expiring')->value('body'));

        Employee::first()->update(['id_expiry_date' => '2026-01-25']); // renewed date resets the marker
        $this->assertSame(1, $jobs->employeeIdExpiry());
    }

    public function test_ended_contract_frees_unit_and_overdue_management_fees_are_caught_up(): void
    {
        $contract = $this->rentWithContacts(['start_date' => '2025-01-01', 'end_date' => '2025-12-31', 'contract_date' => '2025-01-01']);
        $unit = $contract->unit;
        $unit->update(['status' => 'rented', 'current_contract_type' => 'rent_contract', 'current_contract_id' => $contract->id,
            'management_fee' => 100, 'management_fee_cycle' => 'month', 'management_fee_start_date' => '2025-11-10']);
        $jobs = app(DailyOfficeJobs::class);

        $this->assertSame(1, $jobs->releaseEndedContracts());
        $this->assertSame('available', $unit->fresh()->status->value);

        $this->assertSame(3, $jobs->chargeManagementFees()); // Nov 10, Dec 10, Jan 10
        $this->assertSame('2026-02-10', $unit->fresh()->management_fee_next_date->toDateString());
        $this->assertSame(3, OwnerTransaction::whereHas('type', fn ($q) => $q->where('code', 'mancost'))->count());
        $this->assertSame(0, $jobs->chargeManagementFees());
    }

    public function test_daily_command_runs_for_every_accessible_office(): void
    {
        $this->artisan('rowad:daily')->assertSuccessful()->expectsOutputToContain('مكتب الاختبار العقاري');
    }
}
