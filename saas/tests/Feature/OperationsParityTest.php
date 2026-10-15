<?php

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Filament\Admin\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Admin\Resources\InboxMessages\Pages\ListInboxMessages;
use App\Filament\Admin\Resources\NewsletterSubscribers\Pages\ManageNewsletterSubscribers;
use App\Filament\Admin\Resources\OwnerTransactions\Pages\ListOwnerTransactions;
use App\Filament\Admin\Resources\PaymentVouchers\Pages\CreatePaymentVoucher;
use App\Filament\Admin\Resources\SupportTickets\Pages\ListSupportTickets;
use App\Filament\Admin\Resources\Units\Pages\ListUnits;
use App\Models\InboxMessage;
use App\Models\Installment;
use App\Models\NewsletterSubscriber;
use App\Models\OutgoingMessage;
use App\Models\OwnerTransaction;
use App\Models\SupportTicket;
use App\Models\Unit;
use App\Models\UnitReport;
use App\Models\User;
use App\Services\Backups\OfficeBackup;
use App\Services\Contracts\ContractService;
use App\Services\Installments\InstallmentCollector;
use App\Services\Portal\PortalAccounts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Back-office screens restored after the feature-inventory audit. */
class OperationsParityTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-03-10');
        $this->host = 'http://'.$this->initializeTenancy()->domains()->value('domain');
        Filament::setCurrentPanel('admin');
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        Date::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::create(['name' => 'مدير', 'email' => 'admin@office.test', 'password' => 'secret-password']);
        $user->assignRole('admin');
        $this->actingAs($user);

        return $user;
    }

    /** A rented unit with its first installment collected. */
    private function collected(): array
    {
        $unit = $this->makeUnit([[100, 100]], ['title' => 'شقة المراجعة']);
        $client = $this->makeClient();
        $contract = app(ContractService::class)->createRent([
            'unit_id' => $unit->id, 'calendar' => 'gregorian', 'contract_date' => '2026-01-01', 'start_date' => '2026-01-01',
            'end_date' => '2026-12-31', 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month', 'rent_amount' => 1000,
        ], [$client->id => 100]);
        $installment = $contract->installments()->orderBy('due_date')->first();
        app(InstallmentCollector::class)->collect($installment);

        return [$unit, $client, $contract, $installment->refresh()];
    }

    public function test_owner_ledger_settle_reverse_and_voucher_from_selection(): void
    {
        $this->admin();
        [$unit, , , $installment] = $this->collected();
        $entry = OwnerTransaction::where('installment_id', $installment->id)->whereNotNull('owner_id')->firstOrFail();

        Livewire::test(ListOwnerTransactions::class)
            ->assertCanSeeTableRecords([$entry])
            ->filterTable('is_settled', false)->assertCanSeeTableRecords([$entry])
            ->callTableAction('settle', $entry);
        $this->assertTrue($entry->refresh()->is_settled);

        Livewire::test(ListOwnerTransactions::class)->callTableAction('reverse', $entry);
        $this->assertFalse($entry->refresh()->is_settled);

        // legacy statmout: the payment voucher form opens with this owner's entries and their total
        $page = Livewire::withQueryParams(['owner' => $entry->owner_id, 'entries' => (string) $entry->id])->test(CreatePaymentVoucher::class);
        $page->assertSet('data.party_type', 'owner')->assertSet('data.party_id', $entry->owner_id)->assertSet('data.amount', 1000.0);
    }

    public function test_collected_installment_returns_to_pending_unless_the_owner_was_paid(): void
    {
        [, , , $installment] = $this->collected();
        $this->assertGreaterThan(0, $installment->ownerTransactions()->count());

        app(InstallmentCollector::class)->uncollect($installment);
        $this->assertFalse($installment->refresh()->is_collected);
        $this->assertSame(0, $installment->ownerTransactions()->count());

        app(InstallmentCollector::class)->collect($installment);
        $installment->ownerTransactions()->whereNotNull('owner_id')->update(['is_settled' => true]);
        $this->expectException(BusinessRuleException::class);
        app(InstallmentCollector::class)->uncollect($installment->refresh());
    }

    public function test_units_list_finds_units_by_tenant_name(): void
    {
        $this->admin();
        [$unit] = $this->collected();
        $other = $this->makeUnit([[100, 0]], ['title' => 'وحدة أخرى']);

        Livewire::test(ListUnits::class)
            ->filterTable('party', ['name' => 'عميل تجريبي'])
            ->assertCanSeeTableRecords([$unit])->assertCanNotSeeTableRecords([$other]);
    }

    public function test_activity_log_lists_who_changed_what(): void
    {
        $admin = $this->admin();
        $unit = Unit::create(['title' => 'وحدة السجل', 'offer_type' => 'rent']);
        $unit->update(['title' => 'وحدة السجل المعدلة']);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('subject_type', 'unit')
            ->assertCountTableRecords(2)
            ->assertSee('وحدة')
            ->assertSee($admin->name);
    }

    public function test_ticket_reply_reaches_the_portal_and_inbox_reply_is_sent(): void
    {
        $this->admin();
        $owner = $this->makeUnit()->owners()->first();
        $ownerUser = app(PortalAccounts::class)->issue($owner, 'owner@office.test', 'secret-pass-1')['user'];
        $ticket = SupportTicket::create(['owner_id' => $owner->id, 'type' => 'problem', 'subject' => 'تسريب', 'message' => 'يوجد تسريب'] + SupportTicket::newTicketDefaults());

        Livewire::test(ListSupportTickets::class)->callTableAction('reply', $ticket, ['message' => 'سيصلكم الفني غدًا', 'close' => false]);
        $this->assertSame(['answered', 'سيصلكم الفني غدًا'], [$ticket->refresh()->status->value, $ticket->replies()->value('message')]);
        $this->assertSame(1, $ownerUser->notifications()->count());

        $message = InboxMessage::create(['name' => 'زائر', 'email' => 'visitor@example.com', 'message' => 'هل الشقة متاحة؟']);
        Livewire::test(ListInboxMessages::class)->callTableAction('reply', $message, ['channel' => 'mail', 'subject' => 'رد', 'message' => 'نعم متاحة']);
        $this->assertNotNull($message->refresh()->replied_at);
        $this->assertSame('visitor@example.com', OutgoingMessage::where('event', 'inbox_reply')->sole()->recipient);
    }

    public function test_subscribers_import_from_a_contacts_file(): void
    {
        $this->admin();
        $file = UploadedFile::fake()->createWithContent('contacts.csv', "name,email,mobile\nأحمد,ahmed@example.com,0551234567\nسارة,SARA@example.com,\n");
        NewsletterSubscriber::create(['email' => 'ahmed@example.com']);

        Livewire::test(ManageNewsletterSubscribers::class)->callAction('import', ['file' => $file])->assertHasNoActionErrors();
        $this->assertEqualsCanonicalizing(['ahmed@example.com', 'sara@example.com', null], NewsletterSubscriber::pluck('email')->all());
        $this->assertTrue(NewsletterSubscriber::where('mobile', '0551234567')->exists());
    }

    public function test_office_backup_contains_the_office_data(): void
    {
        $this->makeUnit([[100, 0]], ['title' => 'وحدة النسخة']);
        $path = tempnam(sys_get_temp_dir(), 'bk');

        $counts = app(OfficeBackup::class)->write($path);
        $sql = gzdecode(file_get_contents($path));
        unlink($path);

        $this->assertSame(1, $counts['units']);
        $this->assertStringContainsString('وحدة النسخة', $sql);
        $this->assertArrayNotHasKey('sessions', $counts);
    }

    public function test_private_files_are_only_served_to_their_owner(): void
    {
        $unitA = $this->makeUnit([[100, 0]], ['title' => 'أ']);
        $unitB = $this->makeUnit([[100, 0]], ['title' => 'ب']);
        $ownerA = app(PortalAccounts::class)->issue($unitA->owners()->first(), 'a@office.test', 'secret-pass-1')['user'];
        $ownerB = app(PortalAccounts::class)->issue($unitB->owners()->first(), 'b@office.test', 'secret-pass-1')['user'];
        $report = UnitReport::create(['unit_id' => $unitA->id, 'title' => 'تقرير الحالة']);
        $file = $report->addMedia(UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'))->toMediaCollection();
        $idCopy = $unitA->owners()->first()->addMedia(UploadedFile::fake()->create('id.pdf', 10, 'application/pdf'))->toMediaCollection('attachments');

        $this->actingAs($ownerA)->get("{$this->host}/media/{$file->id}")->assertOk();
        $this->actingAs($ownerA)->get("{$this->host}/media/{$idCopy->id}")->assertOk();
        $this->flushSession();
        $this->actingAs($ownerB)->get("{$this->host}/media/{$file->id}")->assertNotFound();
        $this->actingAs($ownerB)->get("{$this->host}/media/{$idCopy->id}")->assertNotFound();
        $this->flushSession();
        $this->actingAs($ownerA)->get("{$this->host}/owner/unit-reports")->assertOk()->assertSee('تقرير الحالة');
    }

    public function test_new_portal_screens_render(): void
    {
        [$unit, $client] = $this->collected();
        $owner = app(PortalAccounts::class)->issue($unit->owners()->first(), 'o@office.test', 'secret-pass-1')['user'];
        $clientUser = app(PortalAccounts::class)->issue($client, 'c@office.test', 'secret-pass-1')['user'];

        $this->actingAs($owner)->get("{$this->host}/owner/sale-contracts")->assertOk();
        $this->actingAs($owner)->get("{$this->host}/owner/owner-transactions")->assertOk();
        $this->flushSession();
        $this->actingAs($clientUser)->get("{$this->host}/client/units")->assertOk()->assertSee('شقة المراجعة');
    }
}
