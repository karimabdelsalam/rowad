<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Models\City;
use App\Models\Client;
use App\Models\InboxMessage;
use App\Models\Owner;
use App\Models\Plan;
use App\Models\SupportTicket;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\Contracts\ContractService;
use App\Services\Finance\VoucherService;
use App\Services\Installments\InstallmentCollector;
use App\Services\Platform\OfficeProvisioner;
use App\Services\Portal\PortalAccounts;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

/**
 * The public demo office (demo.<base-domain>), also used for previews and screenshots:
 *   staff  admin@demo.test / demo-password
 *   owner  owner@demo.test / demo-password     client  client@demo.test / demo-password
 * Visitors open it from the landing page; it sends no real messages, its accounts' passwords
 * cannot be changed, and with DEMO_RESET_DAILY=true it is rebuilt every night.
 */
class CreateDemoOffice extends Command
{
    protected $signature = 'rowad:demo {--fresh : Delete the demo office first}';

    protected $description = 'Create a demo office with sample units, contracts and payments';

    public const PASSWORD = 'demo-password';

    /** role => [email, panel path] */
    public const ACCOUNTS = [
        'admin' => ['admin@demo.test', 'admin'],
        'owner' => ['owner@demo.test', 'owner'],
        'client' => ['client@demo.test', 'client'],
    ];

    public function handle(OfficeProvisioner $provisioner): int
    {
        $domain = 'demo.'.config('rowad.tenancy.base_domain');
        $existing = Tenant::whereHas('domains', fn ($q) => $q->where('domain', $domain))->first();
        if ($existing && ! $this->option('fresh')) {
            $this->info("Demo office already exists: http://{$domain}");

            return self::SUCCESS;
        }
        $existing?->delete();

        $tenant = $provisioner->provision([
            'name' => 'مؤسسة الرواد العقارية', 'subdomain' => 'demo', 'admin_name' => 'عبدالله الشهري',
            'admin_email' => 'admin@demo.test', 'admin_password' => self::PASSWORD,
            'plan_id' => Plan::where('slug', 'professional')->value('id'), 'phone' => '0112345678',
        ]);
        $tenant->forceFill(['status' => Tenant::STATUS_ACTIVE, 'is_demo' => true, 'trial_ends_at' => null])->save();

        $tenant->run(fn () => $this->seedOffice());
        // built clean: the sample data is not a visitor's change
        $tenant->refresh()->forceFill(['demo_changed_at' => null, 'demo_seen_at' => null])->saveQuietly();
        $url = (parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https').'://'.$domain
            .(($port = parse_url((string) config('app.url'), PHP_URL_PORT)) ? ':'.$port : '');
        $this->info("Demo office ready: {$url}");
        $this->table(['', 'login', 'password', 'one click'], collect(self::ACCOUNTS)->map(fn ($a, $role) => [$role, $a[0], self::PASSWORD, "{$url}/demo/{$role}"])->values()->all());

        return self::SUCCESS;
    }

    private function seedOffice(): void
    {
        Date::setTestNow(now()->startOfMonth()->addDays(9)); // stable "today" inside the month
        foreach ([
            'site_name_en' => 'Al Rowad Real Estate', 'phone' => '0112345678', 'mobile' => '0551234567', 'email' => 'info@alrowad.sa',
            'address1' => 'الرياض', 'address2' => 'حي العليا، طريق الملك فهد', 'vat_number' => '300123456700003',
            'commercial_register' => '1010234567', 'fal_license_number' => '1100012345',
            'landing_hero_title' => 'وحدات سكنية وتجارية للإيجار والبيع في الرياض',
            'landing_hero_subtitle' => 'شقق وفلل ومكاتب مُدارة باحترافية — تواصل معنا واحجز معاينتك اليوم',
            'landing_about' => 'مؤسسة الرواد العقارية تدير أكثر من 300 وحدة سكنية وتجارية في الرياض منذ 2009، ويخدم الملاك والمستأجرين بعقود موثقة ومتابعة دقيقة للتحصيل والصيانة.',
            'landing_hero_title_en' => 'Homes and offices for rent in Riyadh', 'landing_whatsapp' => '966551234567',
        ] as $key => $value) {
            Settings::set($key, $value);
        }
        Storage::disk('public')->put('branding/demo-logo.svg', $this->logo());
        Settings::set('logo', 'branding/demo-logo.svg');
        Storage::disk('public')->put('branding/demo-hero.jpg', $this->skyline());
        Settings::set('landing_hero_image', 'branding/demo-hero.jpg', 'website');

        $riyadh = City::firstOrCreate(['name' => 'الرياض'], ['country_code' => 'SA']);
        $ibans = ['SA2180000000608010167001', 'SA5510000000012345670002'];
        $owners = collect([
            ['سعد', 'محمد', 'العتيبي', '0501112233'], ['نورة', 'عبدالرحمن', 'القحطاني', '0502223344'], ['فهد', 'سليمان', 'الدوسري', '0503334455'],
        ])->map(fn ($o, $i) => Owner::create(['first_name' => $o[0], 'father_name' => $o[1], 'family_name' => $o[2], 'mobile' => $o[3], 'id_type' => 'national_id',
            'id_number' => '10'.random_int(10000000, 99999999), 'nationality' => 'سعودي', 'birth_date' => now()->subYears(48 + $i * 6)->subDays(40 * $i)->toDateString(),
            // two owners have their transfer account, the third does not yet (payouts show what is missing)
            'iban' => $ibans[$i] ?? null,
            'bank_id' => isset($ibans[$i]) ? \App\Models\Lookups\Bank::query()->firstOrCreate(['title' => \App\Support\Iban::bank($ibans[$i])[0]])->id : null]));
        $clients = collect([
            ['خالد', 'عبدالله', 'الحربي'], ['ريم', 'فيصل', 'السبيعي'], ['ماجد', 'ناصر', 'الغامدي'], ['هند', 'إبراهيم', 'الزهراني'], ['شركة', 'الأفق', 'للتجارة'],
        ])->map(fn ($c, $i) => Client::create(['first_name' => $c[0], 'father_name' => $c[1], 'family_name' => $c[2], 'mobile' => '055'.(7000000 + $i * 1111), 'calendar' => 'gregorian']
            // one tenant still lacks a birth date, so the demo shows an Ejar checklist with something missing
            + ($i === 4 ? ['kind' => 'company', 'company_name' => 'شركة الأفق للتجارة', 'company_registration_number' => '1010456789', 'vat_number' => '310456789000003'] : [])
            + ($i === 3 ? ['id_type' => 'iqama', 'id_number' => '2'.random_int(100000000, 999999999), 'nationality' => 'أردني']
                : ['id_type' => 'national_id', 'id_number' => '10'.random_int(10000000, 99999999), 'nationality' => 'سعودي', 'birth_date' => now()->subYears(30 + $i * 3)->toDateString()])));
        $districts = [
            \App\Models\District::firstOrCreate(['name' => 'العليا', 'city_id' => $riyadh->id]),
            \App\Models\District::firstOrCreate(['name' => 'النرجس', 'city_id' => $riyadh->id]),
        ];
        $electricity = \App\Models\Lookups\MeterType::where('title', 'كهرباء')->value('id');

        $towers = [
            Building::create(['title' => 'برج الرواد', 'city_id' => $riyadh->id, 'address' => 'حي العليا', 'floors' => 12]),
            Building::create(['title' => 'مجمع النخيل السكني', 'city_id' => $riyadh->id, 'address' => 'حي النرجس', 'floors' => 4]),
        ];
        $palette = [[15, 118, 110], [30, 64, 175], [180, 83, 9], [126, 34, 206], [190, 18, 60], [21, 128, 61], [71, 85, 105], [3, 105, 161]];
        $units = [];
        foreach ([
            ['شقة 101 — 3 غرف', 0, 3, 145, 42000, true], ['شقة 204 — غرفتان', 0, 2, 110, 34000, true], ['مكتب 702', 0, 0, 90, 55000, true],
            ['فيلا 7 — دوبلكس', 1, 5, 380, 120000, true], ['شقة 12 — 4 غرف', 1, 4, 175, 48000, true], ['شقة 15 — 3 غرف', 1, 3, 150, 40000, false],
            ['شقة 305 — غرفتان', 0, 2, 105, 33000, false], ['محل تجاري 3', 1, 0, 60, 65000, false],
        ] as $i => [$title, $tower, $rooms, $area, $rent, $listed]) {
            $unit = Unit::create([
                'title' => $title, 'building_id' => $towers[$tower]->id, 'offer_type' => 'rent', 'city_id' => $riyadh->id, 'district_id' => $districts[$tower]->id,
                'number' => (string) (101 + $i * 101), 'deed_number' => (string) (310105040000 + $i * 7311),
                'rooms' => $rooms ?: null, 'area' => $area, 'rent_amount' => $rent, 'rent_period' => 'year', 'rent_payment_cycle' => 'quarter',
                'is_published' => $listed, 'show_price' => true, 'is_featured' => $i < 2, 'floor' => (string) ($i + 1),
                'details' => 'وحدة مجددة بالكامل، مطبخ راكب، مكيفات سبليت، موقف خاص، قريبة من الخدمات والمدارس.',
                'address' => $towers[$tower]->address,
                // Riyadh: Al Olaya / Al Narjis
                'latitude' => $tower === 0 ? 24.6905 + $i * 0.0004 : 24.8312 + $i * 0.0004, 'longitude' => $tower === 0 ? 46.6853 : 46.6619,
            ]);
            $unit->owners()->attach($owners[$i % 3]->id, ['share_percent' => 100, 'expenses_percent' => 100]);
            if ($electricity) {
                $unit->meters()->create(['meter_type_id' => $electricity, 'number' => (string) (4100230000 + $i * 1597)]);
            }
            if ($i < 6) {
                $unit->addMediaFromString($this->photo($palette[$i], $title))->usingFileName("unit-{$i}.jpg")->toMediaCollection('photos');
            }
            $units[] = $unit;
        }

        // two units for sale on the website, beside the rentals
        $malqa = \App\Models\District::firstOrCreate(['name' => 'الملقا', 'city_id' => $riyadh->id]);
        foreach ([['فيلا 22 — حي الملقا', $malqa, 6, 450, 2350000, 5, 'فيلا حديثة بتشطيب فاخر: مجلس رجال ومجلس نساء، 6 غرف نوم، مسبح، حديقة خلفية، وغرفة سائق. صك إلكتروني جاهز للإفراغ.'],
            ['شقة تمليك 9 — النرجس', $districts[1], 4, 190, 890000, 6, 'شقة تمليك بالدور الثاني بمدخل خاص، 4 غرف، صالة واسعة ومطبخ راكب، مع موقف مظلل. قريبة من طريق الملك سلمان.']] as $k => [$title, $district, $rooms, $area, $price, $color, $details]) {
            $unit = Unit::create(['title' => $title, 'offer_type' => 'sale', 'city_id' => $riyadh->id, 'district_id' => $district->id, 'rooms' => $rooms, 'area' => $area,
                'sale_price' => $price, 'is_published' => true, 'show_price' => true, 'is_featured' => $k === 0, 'floor' => $k === 0 ? null : '2', 'details' => $details,
                'address' => $district->name, 'latitude' => 24.8102 + $k * 0.02, 'longitude' => 46.6013 + $k * 0.05]);
            $unit->owners()->attach($owners[$k]->id, ['share_percent' => 100, 'expenses_percent' => 100]);
            $unit->addMediaFromString($this->photo($palette[$color], $title))->usingFileName("sale-{$k}.jpg")->toMediaCollection('photos');
        }

        $bank = \App\Models\BankAccount::create(['bank_id' => \App\Models\Lookups\Bank::query()->value('id'), 'holder_name' => 'مؤسسة الرواد العقارية',
            'account_number' => '608010167519', 'iban' => 'SA0380000000608010167519', 'is_default' => true]);

        // rented units with history: contracts started months ago, past installments collected
        $contracts = app(ContractService::class);
        $collector = app(InstallmentCollector::class);
        foreach ([[5, 0, 5], [6, 1, 3], [7, 4, 8], [1, 2, 10], [4, 3, 7]] as [$unitIndex, $clientIndex, $monthsAgo]) {
            $start = now()->subMonths($monthsAgo)->startOfMonth();
            $contract = $contracts->createRent([
                'unit_id' => $units[$unitIndex]->id, 'calendar' => 'gregorian', 'contract_date' => $start, 'start_date' => $start,
                'end_date' => $start->copy()->addYear()->subDay(), 'period_unit' => 'year', 'period_count' => 1, 'payment_cycle' => 'month',
                'rent_amount' => round($units[$unitIndex]->rent_amount / 12, 2), 'commission' => 500,
            ], [$clients[$clientIndex]->id => 100]);
            $today = now();
            foreach ($contract->installments()->whereDate('due_date', '<', now()->subMonth())->orderBy('due_date')->get() as $installment) {
                Date::setTestNow($installment->due_date->copy()->addDays(2)); // collected on time, as in real history
                app(VoucherService::class)->createReceipt(['issued_at' => $installment->due_date, 'amount' => $installment->amount,
                    'payment_method' => 'transfer', 'bank_account_id' => $bank->id, 'party_type' => 'client', 'party_id' => $clients[$clientIndex]->id, 'unit_id' => $units[$unitIndex]->id,
                    'reason' => 'سداد إيجار'], [$installment->id]);
            }
            Date::setTestNow($today);
        }
        $collector->collect($units[5]->installments()->where('is_collected', false)->orderBy('due_date')->first());

        // Ejar: most contracts documented, one waiting for the tenant's approval, one not started (its tenant lacks a birth date)
        foreach ([5 => 'documented', 6 => 'documented', 1 => 'documented', 7 => 'awaiting_parties'] as $unitIndex => $status) {
            $contract = \App\Models\RentContract::where('unit_id', $units[$unitIndex]->id)->first();
            $contract->forceFill(['ejar_status' => $status, 'ejar_number' => $status === 'documented' ? (string) (20154000000 + $unitIndex * 48271) : null,
                'ejar_documented_at' => $status === 'documented' ? $contract->start_date->copy()->subDays(2) : null])->save();
        }

        // a land sold in quarterly installments after a down payment, the passed ones collected
        $land = Unit::create(['title' => 'أرض سكنية 58 — حي الملقا', 'offer_type' => 'sale', 'city_id' => $riyadh->id, 'area' => 625,
            'plan_number' => '1120', 'plot_number' => '58', 'address' => 'حي الملقا', 'is_published' => false]);
        $land->owners()->attach($owners[1]->id, ['share_percent' => 100, 'expenses_percent' => 100]);
        $sold = now()->subMonths(8)->startOfMonth();
        $sale = $contracts->createSale(['unit_id' => $land->id, 'calendar' => 'gregorian', 'contract_date' => $sold, 'total_amount' => 950000,
            'payment_type' => 'installment', 'payment_plan' => ['first_payment' => 250000, 'count' => 8, 'cycle' => 'quarter', 'start_date' => $sold->toDateString()],
        ], [$clients[4]->id => 100]);
        foreach ($sale->installments()->whereDate('due_date', '<', now())->get() as $installment) {
            $collector->collect($installment);
        }

        // shared office files (file cloud)
        foreach ([['صورة واجهة برج الرواد', 'tower-front.jpg', $this->photo($palette[0], 'برج الرواد')],
            ['نموذج طلب صيانة', 'maintenance-request.txt', "نموذج طلب صيانة\nاسم المستأجر:\nرقم الوحدة:\nوصف المشكلة:\n"]] as [$title, $name, $content]) {
            \App\Models\OfficeFile::create(['title' => $title])->addMediaFromString($content)->usingFileName($name)->toMediaCollection('file');
        }

        // portal accounts
        app(PortalAccounts::class)->issue($owners[2], 'owner@demo.test', self::PASSWORD);
        app(PortalAccounts::class)->issue($clients[0], 'client@demo.test', self::PASSWORD);

        $admin = User::where('email', 'admin@demo.test')->first();
        Task::create(['created_by' => $admin->id, 'assigned_to' => $admin->id, 'content' => 'التواصل مع المستأجر بخصوص تجديد عقد شقة 15', 'due_date' => now()->addDays(3)]);
        Task::create(['created_by' => $admin->id, 'assigned_to' => $admin->id, 'content' => 'معاينة فيلا 7 مع العميل المهتم', 'due_date' => now()->addDay()]);
        SupportTicket::create(['owner_id' => $owners[2]->id, 'unit_id' => $units[5]->id, 'type' => 'problem', 'subject' => 'تسريب مياه في المطبخ',
            'message' => 'أبلغني المستأجر بوجود تسريب تحت المغسلة.'] + SupportTicket::newTicketDefaults());

        // viewing appointments booked from the website: today, tomorrow and one already done
        $viewings = app(\App\Services\Viewings\ViewingSchedule::class);
        // real today (the demo seeds history on a fixed day of the month), so these show as today / upcoming
        $base = \Carbon\CarbonImmutable::instance(new \DateTimeImmutable('now', new \DateTimeZone(\App\Services\Viewings\ViewingSchedule::timezone())))->startOfDay();
        foreach ([[0, 0, '17:00', 'أحمد السالم', '0559876543'], [3, 0, '19:30', 'منيرة الشمري', '0553214567'],
            [0, 1, '16:30', 'يوسف المالكي', '0547771234'], [3, 2, '18:00', 'عائلة الحربي', '0561239876']] as [$unit, $day, $time, $name, $mobile]) {
            $viewings->book($units[$unit], ['starts_at' => $base->addDays($day)->format('Y-m-d').' '.$time, 'name' => $name, 'mobile' => $mobile], 'staff');
        }
        $past = $viewings->book($units[0], ['starts_at' => $base->subDays(2)->format('Y-m-d').' 18:00', 'name' => 'سلمان العنزي', 'mobile' => '0558882211'], 'staff');
        $viewings->outcome($past, \App\Enums\ViewingStatus::Done);
        \App\Models\Viewing::query()->update(['source' => 'website']);

        // collection ladder on: first notices for this month's late rent, and a formal warning already issued to one tenant
        Settings::set('collection_escalation_enabled', true, 'collection');
        $late = \App\Models\Installment::query()->where('unit_id', $units[4]->id)->where('is_collected', false)->whereDate('due_date', '<', now())->get();
        if ($late->isNotEmpty()) {
            app(\App\Services\Collection\CollectionEscalation::class)->warn($late);
        }
        app(\App\Services\Collection\CollectionEscalation::class)->run();

        // e-signature: the owner of the portal tenant's contract has signed, the tenant still has to
        $signing = app(\App\Services\Signing\ContractSigning::class);
        $signed = \App\Models\RentContract::where('unit_id', $units[5]->id)->first();
        $signing->request($signed);
        \App\Models\ContractSignature::where('role', 'lessor')->whereMorphedTo('signable', $signed)->first()->forceFill([
            'status' => 'signed', 'signature' => $this->signatureDrawing(), 'signed_at' => now()->subDay()->setTime(19, 42),
            'ip' => '188.48.12.7', 'user_agent' => 'Mozilla/5.0 (iPhone)', 'document_hash' => \App\Services\Signing\ContractSigning::fingerprint($signed),
        ])->save();

        // maintenance at every stage: done and rated, waiting for the owner, booked, just reported, in progress
        Settings::set('maintenance_approval_limit', 1000, 'general');
        $maintenance = app(\App\Services\Maintenance\MaintenanceService::class);
        $today = now();
        $report = function (int $unit, int $client, array $data, int $daysAgo, ?array $rgb = null) use ($maintenance, $units, $clients, $today) {
            Date::setTestNow($today->copy()->subDays($daysAgo)->setTime(10, 15));
            $request = $maintenance->open(['unit_id' => $units[$unit]->id] + $data, $clients[$client]);
            if ($rgb !== null) {
                $request->addMediaFromString($this->photo($rgb, $data['title']))->usingFileName("maintenance-{$request->id}.jpg")->toMediaCollection('photos');
            }

            return $request;
        };
        $leak = $report(5, 0, ['category' => 'plumbing', 'title' => 'تسريب ماء تحت مغسلة المطبخ', 'description' => 'تسريب مستمر من الوصلة السفلية والخزانة تبللت.', 'preferred_time' => 'بعد العصر'], 12, $palette[3]);
        $maintenance->schedule($leak, ['technician_name' => 'مؤسسة الإتقان للصيانة', 'technician_phone' => '0559001122', 'scheduled_at' => now()->addDay()->setTime(16, 0), 'estimated_cost' => 380, 'assigned_to' => $admin->id]);
        Date::setTestNow(now()->addDay()->setTime(18, 0));
        $maintenance->complete($leak, ['cost' => 380, 'charge_to' => 'owners', 'payment_method' => 'cash', 'resolution' => 'تغيير خلاط المغسلة والوصلات المرنة واختبار عدم وجود تسريب.']);
        $leak->forceFill(['rating' => 5, 'feedback' => 'الفني حضر في الموعد وأنهى العمل بسرعة. شكرًا لكم.'])->save();
        $ac = $report(5, 0, ['category' => 'ac', 'priority' => 'urgent', 'title' => 'مكيف غرفة النوم الرئيسية لا يبرد', 'description' => 'يعمل المكيف لكن الهواء حار، والوحدة الخارجية تصدر صوتًا عاليًا.'], 2, $palette[1]);
        $maintenance->schedule($ac, ['technician_name' => 'شركة برودة للتكييف', 'technician_phone' => '0558003344', 'scheduled_at' => $today->copy()->addDays(2)->setTime(11, 0), 'estimated_cost' => 1650, 'assigned_to' => $admin->id]);
        $power = $report(1, 2, ['category' => 'electrical', 'priority' => 'urgent', 'title' => 'انقطاع الكهرباء عن أفياش المطبخ', 'preferred_time' => 'أي وقت صباحًا'], 1);
        $maintenance->schedule($power, ['technician_name' => 'فني الكهرباء — أبو أحمد', 'technician_phone' => '0557004455', 'scheduled_at' => $today->copy()->addDay()->setTime(9, 30), 'estimated_cost' => 200, 'assigned_to' => $admin->id]);
        $report(4, 3, ['category' => 'painting', 'title' => 'تقشر الدهان في جدار الصالة', 'description' => 'رطوبة واضحة في الجدار المجاور للحمام.'], 0, $palette[6]);
        $door = $report(7, 4, ['category' => 'carpentry', 'priority' => 'urgent', 'title' => 'باب المحل الزجاجي لا يُقفل'], 3);
        $maintenance->schedule($door, ['technician_name' => 'ورشة الجزيرة للألمنيوم', 'scheduled_at' => $today->copy()->subDay()->setTime(17, 0), 'estimated_cost' => 450]);
        $maintenance->start($door);
        Date::setTestNow($today);
        \App\Models\Letter::create(['type' => 'review', 'calendar' => 'gregorian', 'letter_date' => now(), 'created_by' => $admin->id, 'data' => ['review' => [
            'provider' => 'سعد محمد العتيبي', 'provider_title' => 'owner', 'city_id' => $riyadh->id, 'size' => '625', 'planno' => '1120', 'plotno' => '58',
            'amount' => '1850000', 'about' => 'أرض سكنية على شارعين في موقع مميز',
            'plot' => ['north_type' => 'street', 'north_width' => '20', 'north_length' => '25', 'south_type' => 'neighbour', 'south_length' => '25',
                'east_type' => 'build', 'east_plotno' => '59', 'east_length' => '25', 'west_type' => 'path', 'west_width' => '8', 'west_length' => '25']]]]);
        InboxMessage::create(['source' => 'unit_inquiry', 'unit_id' => $units[0]->id, 'name' => 'أحمد السالم', 'mobile' => '0559876543',
            'message' => 'السلام عليكم، هل الشقة متاحة للمعاينة يوم الخميس؟']);
        Date::setTestNow();
    }

    /** A hand-drawn looking signature (PNG data URI) for the demo's signed party. */
    private function signatureDrawing(): string
    {
        $image = imagecreatetruecolor(360, 120);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 255, 255, 127));
        $ink = imagecolorallocate($image, 15, 23, 42);
        imagesetthickness($image, 3);
        $previous = null;
        for ($x = 20; $x <= 330; $x += 4) {
            $point = [$x, (int) (62 + 26 * sin($x / 17) * cos($x / 53) - ($x > 250 ? ($x - 250) / 3 : 0))];
            if ($previous) {
                imageline($image, $previous[0], $previous[1], $point[0], $point[1], $ink);
            }
            $previous = $point;
        }
        imageline($image, 40, 96, 300, 90, $ink);
        ob_start();
        imagepng($image);

        return 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
    }

    /** A simple generated façade picture so the demo needs no external images. */
    /** A simple vector logo for the demo office (roofline mark + name), stored on the public disk. */
    private function logo(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 96" width="320" height="96">
  <rect x="4" y="4" width="88" height="88" rx="22" fill="#0f766e"/>
  <path d="M22 54 48 30l26 24" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
  <path d="M30 50v22h36V50" fill="none" stroke="#fff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
  <rect x="42" y="58" width="12" height="14" rx="2" fill="#fff"/>
  <text x="108" y="46" font-family="Cairo, Tahoma, Arial, sans-serif" font-size="30" font-weight="800" fill="#0f172a">مؤسسة الرواد</text>
  <text x="108" y="78" font-family="Cairo, Tahoma, Arial, sans-serif" font-size="20" font-weight="600" fill="#0f766e">للخدمات العقارية</text>
</svg>
SVG;
    }

    /** The website's hero for the demo: a city skyline at dusk, lit windows (drawn, so no stock photo is needed). */
    private function skyline(): string
    {
        [$w, $h] = [1920, 900];
        mt_srand(7); // the same picture on every rebuild
        $img = imagecreatetruecolor($w, $h);
        $stops = [[0, [11, 18, 40]], [.55, [22, 78, 99]], [.82, [214, 128, 84]], [1, [246, 190, 120]]];
        for ($y = 0; $y < $h; $y++) {
            $t = $y / $h;
            for ($s = 0; $s < count($stops) - 1 && $t > $stops[$s + 1][0]; $s++);
            [$t0, $c0] = $stops[$s];
            [$t1, $c1] = $stops[min($s + 1, count($stops) - 1)];
            $k = $t1 > $t0 ? ($t - $t0) / ($t1 - $t0) : 0;
            imageline($img, 0, $y, $w, $y, imagecolorallocate($img, ...array_map(fn ($a, $b) => (int) ($a + ($b - $a) * $k), $c0, $c1)));
        }
        $lit = [imagecolorallocate($img, 255, 214, 140), imagecolorallocate($img, 255, 236, 190), imagecolorallocate($img, 120, 200, 210)];
        foreach ([[[16, 40, 52], 380, 640], [[10, 28, 38], 520, 900]] as [$rgb, $minH, $maxH]) { // far row, then near row
            $color = imagecolorallocate($img, ...$rgb);
            for ($x = -40; $x < $w; $x += mt_rand(70, 150)) {
                $bw = mt_rand(60, 130);
                $top = $h - mt_rand($minH, $maxH) * ($x > 1050 && $x < 1250 && $maxH === 900 ? 1.25 : 1) * .62;
                imagefilledrectangle($img, $x, (int) $top, $x + $bw, $h, $color);
                for ($wy = (int) $top + 14; $wy < $h - 20; $wy += 22) {
                    for ($wx = $x + 10; $wx < $x + $bw - 12; $wx += 18) {
                        if (mt_rand(0, 100) < 22) {
                            imagefilledrectangle($img, $wx, $wy, $wx + 7, $wy + 10, $lit[mt_rand(0, 2)]);
                        }
                    }
                }
            }
        }
        ob_start();
        imagejpeg($img, null, 82);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function photo(array $rgb, string $label): string
    {
        $w = 1200;
        $h = 800;
        $img = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) { // sky-to-colour gradient
            $t = $y / $h;
            $c = imagecolorallocate($img, (int) (226 * (1 - $t) + $rgb[0] * $t), (int) (240 * (1 - $t) + $rgb[1] * $t), (int) (250 * (1 - $t) + $rgb[2] * $t));
            imageline($img, 0, $y, $w, $y, $c);
        }
        $wall = imagecolorallocate($img, 245, 240, 230);
        $glass = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], 30);
        imagefilledrectangle($img, 250, 180, 950, 800, $wall);
        for ($row = 0; $row < 5; $row++) {
            for ($col = 0; $col < 5; $col++) {
                imagefilledrectangle($img, 290 + $col * 130, 220 + $row * 110, 380 + $col * 130, 290 + $row * 110, $glass);
            }
        }
        imagefilledrectangle($img, 0, 760, $w, 800, imagecolorallocate($img, 120, 113, 108));
        ob_start();
        imagejpeg($img, null, 85);
        imagedestroy($img);

        return (string) ob_get_clean();
    }
}
