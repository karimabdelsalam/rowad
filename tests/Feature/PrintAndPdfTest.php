<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Filament\Admin\Resources\ReceiptVouchers\Pages\ViewReceiptVoucher;
use App\Models\JournalEntry;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\Finance\VoucherService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsDomain;
use Tests\Concerns\InteractsWithTenancy;
use Tests\TestCase;

/** Every document prints on the office's letterhead and downloads as a PDF; pages and tables offer both. */
class PrintAndPdfTest extends TestCase
{
    use BuildsDomain, InteractsWithTenancy, RefreshDatabase;

    private string $host;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $office = $this->initializeTenancy(['name' => 'مكتب الاختبار العقاري']);
        $this->host = 'http://'.$office->domains()->value('domain');
        $this->admin = User::create(['type' => 'staff', 'name' => 'مدير', 'email' => 'admin@test.local', 'password' => 'secret-password', 'is_super_admin' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownTenancy();
        parent::tearDown();
    }

    public function test_new_documents_print_and_the_print_page_makes_a_pdf(): void
    {
        $this->actingAs($this->admin);
        $voucher = app(VoucherService::class)->createPayment(['issued_at' => '2026-02-02', 'amount' => 450, 'payment_method' => 'cash', 'reason' => 'صيانة مصعد'], notify: false);
        $entry = JournalEntry::query()->where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->sole();
        $request = MaintenanceRequest::create(['code' => 'MNT-2026-00001', 'unit_id' => $this->makeUnit()->id, 'title' => 'تسريب في المطبخ', 'technician_name' => 'أبو فهد']);

        $this->get("{$this->host}/print/journal_entry/{$entry->id}")->assertOk()
            ->assertSee($entry->number)->assertSee('صيانة مصعد')->assertSee('450.00')->assertSee('مكتب الاختبار العقاري')
            ->assertSee(__('تحميل PDF'))->assertSee('vendor/pdf/rowad-pdf.js', false)->assertDontSee('rowadPdf());', false);
        $this->get("{$this->host}/print/maintenance_request/{$request->id}?pdf=1")->assertOk()
            ->assertSee(__('أمر صيانة'))->assertSee('تسريب في المطبخ')->assertSee('أبو فهد')
            ->assertSee("window.addEventListener('load', () => rowadPdf());", false);
        $this->get("{$this->host}/print/payment_voucher/{$voucher->id}")->assertOk()->assertSee($voucher->number);

        // a member of staff without the permission gets nothing
        $this->actingAs(User::create(['type' => 'staff', 'name' => 'موظف', 'email' => 'clerk@test.local', 'password' => 'secret-password']));
        $this->get("{$this->host}/print/journal_entry/{$entry->id}")->assertForbidden();
    }

    public function test_document_pages_offer_print_and_pdf(): void
    {
        $this->actingAs($this->admin);
        Filament::setCurrentPanel('admin');
        $receipt = app(VoucherService::class)->createReceipt(['issued_at' => '2026-02-02', 'amount' => 300, 'payment_method' => 'cash', 'party_name' => 'زائر']);
        $entry = JournalEntry::query()->where('source_type', 'receipt_voucher')->sole();

        Livewire::test(ViewReceiptVoucher::class, ['record' => $receipt->getKey()])
            ->assertActionVisible('print')->assertActionVisible('pdf')
            ->assertActionHasUrl('pdf', route('documents.print', ['type' => 'receipt_voucher', 'id' => $receipt->id, 'pdf' => 1]));
        Livewire::test(ViewJournalEntry::class, ['record' => $entry->getKey()])->assertActionVisible('pdf');
    }
}
