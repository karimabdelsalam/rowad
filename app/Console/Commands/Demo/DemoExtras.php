<?php

namespace App\Console\Commands\Demo;

use App\Enums\EndOfServiceReason;
use App\Filament\Admin\Resources\EndOfServiceSettlements\EndOfServiceSettlementResource;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Building;
use App\Models\Campaign;
use App\Models\Citizen;
use App\Models\CompanyPartner;
use App\Models\ContractTerm;
use App\Models\CustomField;
use App\Models\CustomFieldGroup;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\EmployeePenalty;
use App\Models\EndOfServiceSettlement;
use App\Models\GovernmentLicense;
use App\Models\InboxMessage;
use App\Models\Installment;
use App\Models\LeaveRequest;
use App\Models\Lookups\Bank;
use App\Models\Lookups\DeedType;
use App\Models\NewsletterSubscriber;
use App\Models\Note;
use App\Models\OnlinePayment;
use App\Models\Owner;
use App\Models\Page;
use App\Models\PaymentVoucher;
use App\Models\ReceiptVoucher;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\Unit;
use App\Models\User;
use App\Services\Finance\InvoiceService;
use App\Services\Hr\Payroll;
use App\Services\Payouts\OwnerPayouts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The rest of the public demo office: every screen a visitor opens has believable data — staff and payroll,
 * invoices and online payments, owner payouts, contract terms, licences, campaigns, website pages, branches,
 * more photos per listed unit, deeds and inspection reports. Everything goes through the same services an office
 * uses, so totals and links agree; failures here never stop the demo from being built.
 */
final class DemoExtras
{
    /** @param list<Unit> $units @param Collection<int, Owner> $owners */
    public function __construct(
        private readonly array $units,
        private readonly Collection $owners,
        private readonly Collection $clients,
        private readonly array $towers,
        private readonly BankAccount $bank,
        private readonly User $admin,
        private readonly array $palette,
    ) {}

    public function seed(): void
    {
        foreach (['photos', 'deeds', 'customFields', 'contractTerms', 'directory', 'licences', 'staff', 'payroll',
            'invoices', 'onlinePayments', 'officeCommission', 'ownerPayout', 'officeExpenses', 'communication', 'website', 'branches'] as $step) {
            try {
                DB::transaction(fn () => $this->{$step}());
            } catch (\Throwable $e) {
                report($e); // one section failing leaves the rest of the demo intact
                fwrite(STDERR, "demo extras: {$step} skipped — {$e->getMessage()}\n");
            }
        }
    }

    /** Three more pictures per listed unit (living room, kitchen, bedroom) so galleries have something to show. */
    private function photos(): void
    {
        foreach (Unit::query()->where('is_published', true)->get() as $i => $unit) {
            foreach (['living', 'kitchen', 'bedroom'] as $k => $room) {
                $unit->addMediaFromString($this->interior($room, $this->palette[($i + $k) % count($this->palette)]))
                    ->usingFileName("unit-{$unit->id}-{$room}.jpg")->toMediaCollection('photos');
            }
        }
    }

    private function deeds(): void
    {
        $electronic = DeedType::query()->where('title', 'صك إلكتروني')->value('id');
        foreach (Unit::query()->get() as $i => $unit) {
            $unit->deeds()->create(['deed_type_id' => $electronic, 'number' => $unit->deed_number ?: (string) (310105060000 + $i * 9137),
                'source' => 'كتابة العدل الأولى بالرياض', 'issue_date' => now()->subYears(3 + $i % 4)->subDays(40 * $i)->toDateString()]);
        }
        $categories = collect(['محضر استلام', 'محضر تسليم', 'فحص دوري'])->map(fn ($t) => \App\Models\Lookups\UnitReportCategory::firstOrCreate(['title' => $t]));
        foreach ([[0, 0, 'محضر استلام الوحدة من المالك'], [5, 1, 'محضر تسليم الوحدة للمستأجر'], [3, 2, 'تقرير الفحص الدوري للمكيفات']] as [$u, $c, $title]) {
            $report = $this->units[$u]->reports()->create(['unit_report_category_id' => $categories[$c]->id, 'title' => $title, 'issued_at' => now()->subDays(20 + $u)]);
            $report->addMediaFromString($this->document($title, $this->units[$u]->title))->usingFileName("report-{$u}.jpg")->toMediaCollection();
        }
        $this->units[0]->addMediaFromString($this->document('صك ملكية', $this->units[0]->title.' — رقم '.$this->units[0]->deed_number))
            ->usingFileName('deed.jpg')->toMediaCollection('attachments');
    }

    private function customFields(): void
    {
        $group = CustomFieldGroup::create(['title' => 'مواصفات إضافية']);
        $baths = CustomField::create(['custom_field_group_id' => $group->id, 'type' => 'number', 'title' => 'عدد دورات المياه', 'sort_order' => 1]);
        $lift = CustomField::create(['custom_field_group_id' => $group->id, 'type' => 'checkbox', 'title' => 'مصعد', 'sort_order' => 2]);
        $ac = CustomField::create(['custom_field_group_id' => $group->id, 'type' => 'select', 'title' => 'نوع التكييف', 'options' => ['سبليت', 'مركزي', 'شباك'], 'sort_order' => 3]);
        foreach (array_slice($this->units, 0, 6) as $i => $unit) {
            foreach ([[$baths, (string) (2 + $i % 3)], [$lift, $i < 3 ? '1' : '0'], [$ac, $i === 3 ? 'مركزي' : 'سبليت']] as [$field, $value]) {
                $unit->customFieldValues()->create(['custom_field_id' => $field->id, 'value' => $value]);
            }
        }
    }

    private function contractTerms(): void
    {
        foreach ([
            ['الصيانة الدورية', 'يتحمل المستأجر الصيانة البسيطة الناتجة عن الاستخدام اليومي، ويتحمل المؤجر الإصلاحات الجوهرية في الهيكل والتمديدات الرئيسية.'],
            ['استهلاك الكهرباء والمياه', 'يلتزم المستأجر بسداد فواتير الكهرباء والمياه طوال مدة العقد، وتُقرأ العدادات عند الاستلام والتسليم.'],
            ['التأمين', 'يدفع المستأجر مبلغ تأمين يعادل قيمة شهر واحد، يُرد عند انتهاء العقد بعد خصم أي أضرار.'],
            ['الإخلاء', 'يلتزم الطرف الراغب في عدم التجديد بإشعار الطرف الآخر كتابيًا قبل ستين يومًا من نهاية العقد.'],
            ['التعديلات', 'لا يحق للمستأجر إجراء أي تعديلات إنشائية على الوحدة دون موافقة كتابية من المؤجر.'],
        ] as [$title, $content]) {
            ContractTerm::create(['title' => $title, 'content' => $content]);
        }
    }

    /** People and organisations the office deals with. */
    private function directory(): void
    {
        foreach ([['محمد سعيد القرني', '1056783421'], ['عبير خالد الدوسري', '1067894512'], ['راشد فهد المطيري', '1078905613']] as [$name, $id]) {
            Citizen::create(['name' => $name, 'id_type' => 'national_id', 'id_number' => $id, 'nationality' => 'سعودي']);
        }
        foreach ([['عبدالله', 'سعد', 'الشهري', 60], ['تركي', 'ناصر', 'العنزي', 40]] as [$first, $father, $family, $share]) {
            CompanyPartner::create(['first_name' => $first, 'father_name' => $father, 'family_name' => $family, 'share_percent' => $share,
                'id_type' => 'national_id', 'id_number' => '10'.random_int(10000000, 99999999), 'nationality' => 'سعودي', 'mobile' => '05'.random_int(10000000, 99999999), 'city' => 'الرياض']);
        }
        foreach (['أمانة منطقة الرياض', 'الشركة السعودية للكهرباء', 'شركة المياه الوطنية', 'وزارة البلديات والإسكان'] as $title) {
            \App\Models\Lookups\GovernmentOrganization::firstOrCreate(['title' => $title]);
        }
        foreach (['الغرفة التجارية بالرياض', 'الهيئة العامة للعقار', 'مؤسسة الصيانة المتكاملة'] as $title) {
            \App\Models\Lookups\TradeOrganization::firstOrCreate(['title' => $title]);
        }
        foreach (['أفراد', 'شركات', 'جهات حكومية'] as $title) {
            \App\Models\Lookups\ClientCategory::firstOrCreate(['title' => $title]);
        }
    }

    private function licences(): void
    {
        foreach ([['السجل التجاري', '1010456123', 240], ['رخصة فال للوساطة العقارية', '1200012345', 35], ['رخصة البلدية للمكتب', '4410098765', 380], ['شهادة الزكاة والضريبة', '3101234567', 120]] as [$title, $number, $days]) {
            GovernmentLicense::create(['title' => $title, 'number' => $number, 'expiry_date' => now()->addDays($days)->toDateString()]);
        }
    }

    /** Five staff (one who left with an end-of-service settlement), leave, an advance and a deduction. */
    private function staff(): void
    {
        $bankId = fn (string $name) => Bank::query()->firstOrCreate(['title' => $name])->id;
        $people = [
            ['فيصل', 'عبدالرحمن', 'القحطاني', 'مدير العقارات', 'الإدارة', 14000, 3500, 1000, 6, 'مصرف الراجحي', 2.0],
            ['سارة', 'محمد', 'الغامدي', 'محاسبة', 'المالية', 9500, 2375, 800, 3, 'البنك الأهلي السعودي', 0],
            ['محمد', 'علي', 'الزهراني', 'مسوق عقاري', 'المبيعات', 7000, 1750, 700, 2, 'بنك الرياض', 2.5],
            ['نوف', 'سليمان', 'العتيبي', 'خدمة العملاء', 'الإدارة', 6500, 1625, 600, 1, 'مصرف الإنماء', 0],
        ];
        $staff = [];
        foreach ($people as $i => [$first, $father, $family, $job, $dept, $salary, $housing, $transport, $years, $bank, $commission]) {
            $staff[] = Employee::create([
                'first_name' => $first, 'father_name' => $father, 'family_name' => $family, 'job_title' => $job, 'department' => $dept,
                'salary' => $salary, 'housing_allowance' => $housing, 'transport_allowance' => $transport, 'rent_commission' => $commission,
                'start_date' => now()->subYears($years)->subMonths($i + 1)->startOfMonth()->toDateString(),
                'id_type' => 'national_id', 'id_number' => '10'.random_int(10000000, 99999999), 'nationality' => 'سعودي',
                'birth_date' => now()->subYears(28 + $i * 4)->toDateString(), 'mobile' => '050'.(1200000 + $i * 3311),
                'gosi_number' => (string) (40000000 + $i * 731), 'bank_id' => $bankId($bank),
                'id_expiry_date' => now()->addDays([420, 25, 610, 300][$i])->toDateString(), // one ID renews this month: the expiry alert shows
                'iban' => ['SA0380000000608010167601', 'SA6610000000012345671234', 'SA2420000000002083459012', 'SA0905000068201234567000'][$i],
            ]);
        }
        $yousef = Employee::create(['first_name' => 'يوسف', 'father_name' => 'أحمد', 'family_name' => 'الشمري', 'job_title' => 'مندوب تحصيل', 'department' => 'المالية',
            'salary' => 6000, 'housing_allowance' => 1500, 'transport_allowance' => 500, 'start_date' => now()->subYears(4)->subMonths(5)->toDateString(),
            'id_type' => 'national_id', 'id_number' => '10'.random_int(10000000, 99999999), 'nationality' => 'سعودي', 'mobile' => '0509988776',
            'status' => 'terminated', 'termination_date' => now()->subMonth()->endOfMonth()->toDateString()]);
        EndOfServiceSettlement::create(EndOfServiceSettlementResource::compute([
            'employee_id' => $yousef->id, 'termination_date' => $yousef->termination_date->toDateString(), 'reason' => EndOfServiceReason::Resignation->value,
            'last_wage' => $yousef->totalWage(), 'leave_balance_days' => 6, 'other_dues' => 0, 'deductions' => 0, 'notes' => 'استقالة بعد إشعار مسبق 30 يومًا',
        ]) + ['created_by' => $this->admin->id]);

        LeaveRequest::create(['employee_id' => $staff[1]->id, 'type' => 'annual', 'start_date' => now()->subDays(45)->toDateString(), 'end_date' => now()->subDays(36)->toDateString(),
            'days' => 10, 'status' => 'approved', 'reason' => 'إجازة سنوية', 'approved_by' => $this->admin->id, 'approved_at' => now()->subDays(50), 'created_by' => $this->admin->id]);
        LeaveRequest::create(['employee_id' => $staff[2]->id, 'type' => 'sick', 'start_date' => now()->subDays(12)->toDateString(), 'end_date' => now()->subDays(11)->toDateString(),
            'days' => 2, 'status' => 'approved', 'reason' => 'تقرير طبي', 'approved_by' => $this->admin->id, 'approved_at' => now()->subDays(12), 'created_by' => $this->admin->id]);
        LeaveRequest::create(['employee_id' => $staff[3]->id, 'type' => 'annual', 'start_date' => now()->addDays(14)->toDateString(), 'end_date' => now()->addDays(20)->toDateString(),
            'days' => 7, 'status' => 'pending', 'reason' => 'سفر عائلي', 'created_by' => $this->admin->id]);
        $advance = EmployeeAdvance::create(['employee_id' => $staff[2]->id, 'amount' => 6000, 'installment_amount' => 1000, 'remaining' => 6000,
            'issued_at' => now()->subMonths(2)->startOfMonth()->toDateString(), 'notes' => 'سلفة على الراتب تُسدد على 6 أشهر', 'created_by' => $this->admin->id]);
        $voucher = app(\App\Services\Finance\VoucherService::class)->createPayment(['party_type' => 'employee', 'party_id' => $staff[2]->id,
            'party_name' => $staff[2]->full_name, 'amount' => 6000, 'payment_method' => 'transfer', 'bank_account_id' => $this->bank->id,
            'issued_at' => $advance->issued_at, 'reason' => 'سلفة للموظف '.$staff[2]->full_name], notify: false);
        $advance->forceFill(['payment_voucher_id' => $voucher->id])->save();
        EmployeePenalty::create(['employee_id' => $staff[3]->id, 'amount' => 150, 'reason' => 'late', 'reason_text' => 'تأخر متكرر عن الدوام',
            'for_month' => (int) now()->subMonth()->month, 'for_year' => (int) now()->subMonth()->year, 'issued_at' => now()->subMonth()->startOfMonth()->addDays(20)->toDateString()]);
    }

    /** Last month's payroll approved and paid (vouchers, advance instalment deducted); this month's as a draft. */
    private function payroll(): void
    {
        $payroll = app(Payroll::class);
        $last = now()->subMonth();
        $run = $payroll->generate((int) $last->year, (int) $last->month);
        $payroll->approve($run);
        $payroll->pay($run->refresh(), $this->bank, 'transfer');
        $payroll->generate((int) now()->year, (int) now()->month);
    }

    /** Tax invoices for the company tenant, a simplified one for an individual. */
    private function invoices(): void
    {
        $invoices = app(InvoiceService::class);
        $collected = fn (Unit $unit, int $n) => Installment::query()->with(['unit', 'contract.clients'])->where('unit_id', $unit->id)->where('is_collected', true)->whereNull('invoice_id')->orderBy('due_date')->limit($n)->get();
        foreach ([[$this->units[7], 2], [$this->units[5], 1], [$this->units[6], 1]] as [$unit, $n]) {
            if (($items = $collected($unit, $n))->isNotEmpty()) {
                $invoices->fromInstallments($items);
            }
        }
    }

    /** Rent paid online through the tenant's link (one paid, one declined card, one still open). */
    private function onlinePayments(): void
    {
        $paid = Installment::query()->with('contract.clients')->where('unit_id', $this->units[6]->id)->where('is_collected', true)->whereNotNull('receipt_voucher_id')->latest('due_date')->first();
        $open = Installment::query()->with('contract.clients')->where('is_collected', false)->where('type', '!=', 'expenses')->orderBy('due_date')->limit(2)->get();
        $rows = array_filter([
            $paid ? ['installment_id' => $paid->id, 'amount' => $paid->amount, 'status' => 'paid', 'paid_at' => $paid->collected_at ?? $paid->due_date, 'receipt_voucher_id' => $paid->receipt_voucher_id,
                'gateway_id' => 'pay_'.Str::lower(Str::random(20)), 'payer_name' => $paid->contract?->clients->first()?->full_name, 'source' => 'mada', 'payload' => ['source' => ['type' => 'mada', 'company' => 'mada']]] : null,
            ($i = $open->get(0)) ? ['installment_id' => $i->id, 'amount' => $i->amount, 'status' => 'failed', 'failure' => 'insufficient_funds',
                'gateway_id' => 'pay_'.Str::lower(Str::random(20)), 'payer_name' => $i->contract?->clients->first()?->full_name, 'source' => 'creditcard'] : null,
            ($i = $open->get(1)) ? ['installment_id' => $i->id, 'amount' => $i->amount, 'status' => 'initiated'] : null,
        ]);
        foreach ($rows as $row) {
            OnlinePayment::create($row + ['token' => Str::random(40), 'gateway' => 'moyasar', 'currency' => 'SAR']);
        }
    }

    /** Owners with a bank account were paid last month's dues in one transferred run. */
    private function ownerPayout(): void
    {
        $payouts = app(OwnerPayouts::class);
        $until = now()->subMonth()->endOfMonth();
        $ids = $payouts->candidates($until)->where('ready', true)->map(fn ($row) => $row['owner']->id)->values()->all();
        if ($ids !== []) {
            $run = $payouts->prepare($ids, $until, $this->bank, $until->copy()->addDays(3), 'توزيع إيجارات الشهر الماضي');
            $payouts->markTransferred($run);
        }
    }

    private function communication(): void
    {
        Campaign::create(['channel' => 'sms', 'audience' => 'renters', 'subject' => 'تذكير بموعد الصيانة الدورية',
            'body' => 'عزيزنا المستأجر، نود إشعاركم بأن الصيانة الدورية للمكيفات ستكون يوم الأحد القادم من 9 صباحًا حتى 1 ظهرًا. شكرًا لتعاونكم.',
            'status' => 'sent', 'recipients_count' => 5, 'sent_count' => 5, 'created_by' => $this->admin->id]);
        Campaign::create(['channel' => 'whatsapp', 'audience' => 'owners', 'subject' => 'تقرير الربع الثالث',
            'body' => 'تقرير أداء عقاراتكم للربع الثالث جاهز في بوابة الملاك: نسبة الإشغال، المحصل، والمصروفات.', 'status' => 'draft', 'created_by' => $this->admin->id]);
        foreach ([['سلطان الحربي', 'sultan.h@example.com', '0551112233'], ['أمل العمري', 'amal.o@example.com', null], ['خالد بن سعيد', null, '0544455667'],
            ['منى الدوسري', 'mona.d@example.com', '0533344556'], ['شركة البيان', 'info@albayan.example.com', null], ['ناصر القحطاني', 'nasser.q@example.com', '0566677889']] as [$name, $email, $mobile]) {
            NewsletterSubscriber::create(['name' => $name, 'email' => $email, 'mobile' => $mobile]);
        }
        InboxMessage::create(['source' => 'contact', 'name' => 'شركة الإعمار المتقدمة', 'email' => 'ops@emaar-adv.example.com',
            'subject' => 'إدارة مجمع سكني', 'message' => 'نرغب في التعاقد معكم لإدارة مجمع سكني من 24 وحدة في حي الياسمين، نأمل التواصل لعرض الخدمات.']);
        InboxMessage::create(['source' => 'unit_inquiry', 'unit_id' => $this->units[3]->id, 'name' => 'منيرة الشمري', 'mobile' => '0553214567',
            'message' => 'هل يمكن تقسيط الإيجار شهريًا للفيلا؟', 'read_at' => now()->subDay(),
            'reply' => 'أهلًا بك، نعم يمكن السداد الشهري بعد موافقة المالك. يسعدنا حجز موعد معاينة لك.', 'replied_at' => now()->subHours(20)]);
        InboxMessage::create(['source' => 'contact', 'name' => 'فهد العنزي', 'mobile' => '0547788990', 'message' => 'أبحث عن مكتب تجاري بمساحة 100 متر في العليا.']);

        $owner = $this->owners[2];
        $ticket = SupportTicket::create(['owner_id' => $owner->id, 'unit_id' => $this->units[2]->id, 'type' => 'enquiry', 'subject' => 'موعد تحويل مستحقات الشهر',
            'message' => 'متى يتم تحويل مستحقات هذا الشهر؟'] + SupportTicket::newTicketDefaults());
        $ticket->replies()->create(['user_id' => $this->admin->id, 'message' => 'يتم التحويل في الأسبوع الأول من كل شهر بعد تحصيل الإيجارات، وستصلك رسالة عند التنفيذ.']);
        $ticket->forceFill(['status' => 'answered'])->save();
        SupportTicket::create(['owner_id' => $this->owners[0]->id, 'unit_id' => $this->units[0]->id, 'type' => 'suggestion', 'subject' => 'اقتراح تحديث المطبخ',
            'message' => 'أقترح تحديث خزائن المطبخ قبل التأجير القادم لرفع قيمة الإيجار.'] + SupportTicket::newTicketDefaults());

        foreach ([['تجديد عقد شقة 204 مع المستأجر قبل نهاية الشهر', 6], ['متابعة سداد الدفعة المتأخرة — محل تجاري 3', 1], ['تصوير الشقة 305 بعد الصيانة لنشرها على الموقع', 4]] as [$content, $days]) {
            Task::create(['created_by' => $this->admin->id, 'assigned_to' => $this->admin->id, 'content' => $content, 'due_date' => now()->addDays($days)]);
        }
        Note::create(['user_id' => $this->admin->id, 'content' => 'اجتماع مع ملاك برج الرواد يوم الثلاثاء الساعة 7 مساءً لمناقشة رفع الإيجارات للعام القادم.']);
    }

    /** Pages of the office website. */
    private function website(): void
    {
        foreach ([['من نحن', 'about', 'header', 'مؤسسة الرواد العقارية تدير أكثر من 300 وحدة سكنية وتجارية في الرياض منذ 2009، وتقدم خدمات التأجير والبيع وإدارة الأملاك للملاك والمستثمرين.'],
            ['سياسة الخصوصية', 'privacy', 'footer', 'نحافظ على سرية بياناتك ولا نشاركها مع أي طرف ثالث إلا لتنفيذ الخدمة المطلوبة أو بطلب من جهة رسمية.'],
            ['الشروط والأحكام', 'terms', 'footer', 'الأسعار المعروضة قابلة للتغيير، وتخضع جميع العقود لنظام إيجار والأنظمة المعمول بها في المملكة العربية السعودية.']] as $i => [$title, $slug, $position, $content]) {
            Page::create(['title' => $title, 'slug' => $slug, 'position' => $position, 'type' => 'content', 'content' => $content, 'sort_order' => $i]);
        }
    }

    /** Two branches: Al Olaya (head office) and Al Narjis, each with its own buildings, units and vouchers. */
    /**
     * The office's management commission: 10% of every rent collected, taken from the owner's share (before the payout
     * nets it), and a sale brokered last month — so the books have revenue to show.
     */
    private function officeCommission(): void
    {
        $type = \App\Models\Lookups\TransactionType::query()->where('code', 'commission')->value('id');
        $collected = Installment::query()->where('is_collected', true)->whereNotNull('receipt_voucher_id')->with('unit.owners')->get();
        foreach ($collected as $installment) {
            foreach ($installment->unit->owners as $owner) {
                $share = ((float) $installment->amount - (float) $installment->buyer_commission) * (float) $owner->pivot->share_percent / 100;
                \App\Models\OwnerTransaction::create(['owner_id' => $owner->id, 'unit_id' => $installment->unit_id, 'installment_id' => $installment->id,
                    'contract_type' => $installment->contract_type, 'contract_id' => $installment->contract_id, 'source' => 'rent',
                    'due_date' => $installment->due_date, 'transaction_type_id' => $type, 'direction' => 'credit', 'amount' => round($share * 0.10, 2)]);
            }
        }
        app(\App\Services\Finance\VoucherService::class)->createReceipt(['issued_at' => now()->subMonth()->startOfMonth()->addDays(11)->toDateString(),
            'amount' => 57500, 'vat_amount' => 7500, 'payment_method' => 'transfer', 'bank_account_id' => $this->bank->id, 'party_name' => 'سلمان بن فهد الشمري',
            'reason' => 'عمولة السعي في بيع فيلا حي الملقا (2.5%) شاملة الضريبة',
            'account_id' => \App\Models\Account::query()->where('system_key', 'sale_commission')->value('id')]);
    }

    /** The office's own running costs for the last months, each on its expense account, so the books show a profit and loss. */
    private function officeExpenses(): void
    {
        $account = fn (string $code) => \App\Models\Account::query()->where('code', $code)->value('id');
        $vouchers = app(\App\Services\Finance\VoucherService::class);
        foreach (range(3, 1) as $monthsAgo) {
            $day = now()->subMonths($monthsAgo)->startOfMonth();
            foreach ([
                ['52', 4500, 'إيجار مقر المؤسسة', 'مؤسسة الأفق للعقارات', 'transfer'],
                ['52', 640 + $monthsAgo * 35, 'فاتورة الكهرباء والإنترنت', 'الشركة السعودية للكهرباء', 'transfer'],
                ['54', 1200, 'إعلانات ممولة للوحدات المتاحة', 'منصة إعلانات عقارية', 'card'],
                ['53', 180 + $monthsAgo * 20, 'ضيافة وقرطاسية', 'مكتبة جرير', 'cash'],
            ] as $i => [$code, $amount, $reason, $party, $method]) {
                $vouchers->createPayment(['issued_at' => $day->copy()->addDays(2 + $i * 3)->toDateString(), 'amount' => $amount, 'payment_method' => $method,
                    'bank_account_id' => $method === 'cash' ? null : $this->bank->id, 'party_name' => $party, 'reason' => $reason, 'account_id' => $account($code)],
                    withVat: $code !== '52' || $i === 1, notify: false);
            }
        }
    }

    private function branches(): void
    {
        $city = $this->towers[0]->city_id;
        $main = Branch::create(['name' => 'الفرع الرئيسي — العليا', 'city_id' => $city, 'phone' => '0112345678', 'address' => 'طريق الملك فهد، حي العليا']);
        $north = Branch::create(['name' => 'فرع النرجس', 'city_id' => $city, 'phone' => '0112349876', 'address' => 'طريق أنس بن مالك، حي النرجس']);
        foreach ($this->towers as $i => $tower) {
            $branch = $i === 0 ? $main : $north;
            Building::withoutGlobalScopes()->whereKey($tower->id)->update(['branch_id' => $branch->id]);
            Unit::withoutGlobalScopes()->where('building_id', $tower->id)->update(['branch_id' => $branch->id]);
        }
        Unit::withoutGlobalScopes()->whereNull('branch_id')->update(['branch_id' => $north->id]); // the villas and land up north
        foreach ([ReceiptVoucher::class, PaymentVoucher::class] as $model) {
            $model::withoutGlobalScopes()->whereNotNull('unit_id')->each(fn ($v) => $v->forceFill(['branch_id' => Unit::withoutGlobalScopes()->whereKey($v->unit_id)->value('branch_id')])->saveQuietly());
            $model::withoutGlobalScopes()->whereNull('branch_id')->update(['branch_id' => $main->id]);
        }
    }

    /** A simple drawn interior: wall, floor, window with sky, and the room's furniture. */
    private function interior(string $room, array $rgb): string
    {
        [$w, $h] = [1200, 800];
        $img = imagecreatetruecolor($w, $h);
        $c = fn (int $r, int $g, int $b) => imagecolorallocate($img, $r, $g, $b);
        $mix = fn (array $a, int $to, float $k) => array_map(fn ($v) => (int) ($v + ($to - $v) * $k), $a);
        imagefilledrectangle($img, 0, 0, $w, $h, $c(...$mix($rgb, 255, .86)));                 // wall
        imagefilledrectangle($img, 0, 560, $w, $h, $c(196, 160, 118));                      // wooden floor
        for ($x = 0; $x < $w; $x += 120) {
            imageline($img, $x, 560, $x - 60, $h, $c(176, 140, 100));
        }
        imagefilledrectangle($img, 0, 552, $w, 560, $c(240, 236, 228));                     // skirting
        imagefilledrectangle($img, 720, 110, 1060, 430, $c(250, 250, 250));                 // window frame
        for ($y = 126; $y < 414; $y++) {                                                     // sky
            $t = ($y - 126) / 288;
            imageline($img, 736, $y, 1044, $y, $c((int) (120 + 110 * $t), (int) (180 + 50 * $t), (int) (235 - 10 * $t)));
        }
        imagefilledrectangle($img, 886, 126, 894, 414, $c(250, 250, 250));
        $accent = $c(...$rgb);
        match ($room) {
            'living' => (function () use ($img, $c, $accent) {
                imagefilledrectangle($img, 120, 400, 640, 560, $accent);                        // sofa
                imagefilledrectangle($img, 120, 340, 640, 420, $c(...array_map(fn ($v) => max(0, $v - 30), [120, 120, 120])));
                imagefilledrectangle($img, 150, 330, 610, 400, $accent);
                imagefilledellipse($img, 380, 640, 360, 70, $c(80, 70, 60));                    // rug + table
                imagefilledrectangle($img, 300, 600, 460, 625, $c(110, 80, 60));
                imagefilledrectangle($img, 200, 150, 420, 300, $c(255, 255, 255));             // painting
                imagefilledrectangle($img, 214, 164, 406, 286, $accent);
            })(),
            'kitchen' => (function () use ($img, $c, $accent) {
                imagefilledrectangle($img, 80, 380, 680, 560, $c(245, 245, 245));                // base cabinets
                imagefilledrectangle($img, 80, 370, 680, 392, $c(60, 60, 64));                   // counter
                for ($x = 80; $x < 680; $x += 150) {
                    imagerectangle($img, $x + 8, 404, $x + 142, 548, $c(210, 210, 210));
                    imagefilledrectangle($img, $x + 66, 470, $x + 84, 476, $c(120, 120, 120));
                }
                imagefilledrectangle($img, 80, 140, 680, 280, $accent);                          // upper cabinets
                for ($x = 80; $x < 680; $x += 150) {
                    imagerectangle($img, $x + 8, 148, $x + 142, 272, $c(255, 255, 255));
                }
            })(),
            default => (function () use ($img, $c, $accent) {
                imagefilledrectangle($img, 140, 300, 220, 560, $c(120, 90, 70));                 // headboard
                imagefilledrectangle($img, 140, 440, 660, 560, $c(250, 250, 250));               // bed
                imagefilledrectangle($img, 300, 440, 660, 560, $accent);                         // cover
                imagefilledellipse($img, 260, 430, 120, 50, $c(255, 255, 255));                  // pillow
                imagefilledrectangle($img, 680, 470, 740, 560, $c(150, 115, 85));                // side table
                imagefilledellipse($img, 710, 440, 50, 50, $c(255, 220, 140));                   // lamp
            })(),
        };
        ob_start();
        imagejpeg($img, null, 84);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    /** A scanned-looking document page: a heading band, a title and lines of text. */
    private function document(string $title, string $subtitle): string
    {
        $img = imagecreatetruecolor(900, 1200);
        imagefilledrectangle($img, 0, 0, 900, 1200, imagecolorallocate($img, 251, 250, 246));
        imagefilledrectangle($img, 60, 60, 840, 150, imagecolorallocate($img, 15, 118, 110));
        imagefilledrectangle($img, 60, 190, 560, 214, imagecolorallocate($img, 60, 60, 60));    // title bar (glyph-free, no font needed)
        imagefilledrectangle($img, 60, 236, 420, 252, imagecolorallocate($img, 150, 150, 150));
        $line = imagecolorallocate($img, 205, 205, 200);
        for ($y = 300; $y < 1040; $y += 36) {
            imagefilledrectangle($img, 60, $y, $y % 5 === 0 ? 620 : 840, $y + 12, $line);
        }
        imageellipse($img, 720, 1080, 150, 150, imagecolorallocate($img, 15, 118, 110));      // stamp
        imageellipse($img, 720, 1080, 130, 130, imagecolorallocate($img, 15, 118, 110));
        ob_start();
        imagejpeg($img, null, 80);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
