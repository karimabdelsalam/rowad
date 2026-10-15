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

/**
 * A realistic demo office for previews and screenshots: http://demo.<base-domain>:8000
 *   staff  admin@demo.test / demo-password
 *   owner  owner@demo.test / demo-password     client  client@demo.test / demo-password
 */
class CreateDemoOffice extends Command
{
    protected $signature = 'rowad:demo {--fresh : Delete the demo office first}';

    protected $description = 'Create a demo office with sample units, contracts and payments';

    public const PASSWORD = 'demo-password';

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
        $tenant->forceFill(['status' => Tenant::STATUS_ACTIVE])->save();

        $tenant->run(fn () => $this->seedOffice());
        $this->info("Demo office ready: http://{$domain}:8000  (admin@demo.test / ".self::PASSWORD.')');

        return self::SUCCESS;
    }

    private function seedOffice(): void
    {
        Date::setTestNow(now()->startOfMonth()->addDays(9)); // stable "today" inside the month
        foreach ([
            'site_name_en' => 'Al Rowad Real Estate', 'phone' => '0112345678', 'mobile' => '0551234567', 'email' => 'info@alrowad.sa',
            'address1' => 'الرياض', 'address2' => 'حي العليا، طريق الملك فهد', 'vat_number' => '300123456700003',
            'landing_hero_title' => 'وحدات سكنية وتجارية للإيجار في الرياض',
            'landing_hero_subtitle' => 'شقق وفلل ومكاتب مُدارة باحترافية — تواصل معنا واحجز معاينتك اليوم',
            'landing_about' => 'مؤسسة الرواد العقارية تدير أكثر من 300 وحدة سكنية وتجارية في الرياض منذ 2009، ويخدم الملاك والمستأجرين بعقود موثقة ومتابعة دقيقة للتحصيل والصيانة.',
            'landing_hero_title_en' => 'Homes and offices for rent in Riyadh', 'landing_whatsapp' => '966551234567',
        ] as $key => $value) {
            Settings::set($key, $value);
        }

        $riyadh = City::firstOrCreate(['name' => 'الرياض'], ['country_code' => 'SA']);
        $owners = collect([
            ['سعد', 'محمد', 'العتيبي', '0501112233'], ['نورة', 'عبدالرحمن', 'القحطاني', '0502223344'], ['فهد', 'سليمان', 'الدوسري', '0503334455'],
        ])->map(fn ($o) => Owner::create(['first_name' => $o[0], 'father_name' => $o[1], 'family_name' => $o[2], 'mobile' => $o[3], 'id_type' => 'national_id', 'id_number' => '10'.random_int(10000000, 99999999)]));
        $clients = collect([
            ['خالد', 'عبدالله', 'الحربي'], ['ريم', 'فيصل', 'السبيعي'], ['ماجد', 'ناصر', 'الغامدي'], ['هند', 'إبراهيم', 'الزهراني'], ['شركة', 'الأفق', 'للتجارة'],
        ])->map(fn ($c, $i) => Client::create(['first_name' => $c[0], 'father_name' => $c[1], 'family_name' => $c[2], 'mobile' => '055'.(7000000 + $i * 1111), 'calendar' => 'gregorian']));

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
                'title' => $title, 'building_id' => $towers[$tower]->id, 'offer_type' => 'rent', 'city_id' => $riyadh->id,
                'rooms' => $rooms ?: null, 'area' => $area, 'rent_amount' => $rent, 'rent_period' => 'year', 'rent_payment_cycle' => 'quarter',
                'is_published' => $listed, 'show_price' => true, 'is_featured' => $i < 2, 'floor' => (string) ($i + 1),
                'details' => 'وحدة مجددة بالكامل، مطبخ راكب، مكيفات سبليت، موقف خاص، قريبة من الخدمات والمدارس.',
                'address' => $towers[$tower]->address,
                // Riyadh: Al Olaya / Al Narjis
                'latitude' => $tower === 0 ? 24.6905 + $i * 0.0004 : 24.8312 + $i * 0.0004, 'longitude' => $tower === 0 ? 46.6853 : 46.6619,
            ]);
            $unit->owners()->attach($owners[$i % 3]->id, ['share_percent' => 100, 'expenses_percent' => 100]);
            if ($i < 6) {
                $unit->addMediaFromString($this->photo($palette[$i], $title))->usingFileName("unit-{$i}.jpg")->toMediaCollection('photos');
            }
            $units[] = $unit;
        }

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
                    'payment_method' => 'transfer', 'party_type' => 'client', 'party_id' => $clients[$clientIndex]->id, 'unit_id' => $units[$unitIndex]->id,
                    'reason' => 'سداد إيجار'], [$installment->id]);
            }
            Date::setTestNow($today);
        }
        $collector->collect($units[5]->installments()->where('is_collected', false)->orderBy('due_date')->first());

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
        \App\Models\Letter::create(['type' => 'review', 'calendar' => 'gregorian', 'letter_date' => now(), 'created_by' => $admin->id, 'data' => ['review' => [
            'provider' => 'سعد محمد العتيبي', 'provider_title' => 'owner', 'city_id' => $riyadh->id, 'size' => '625', 'planno' => '1120', 'plotno' => '58',
            'amount' => '1850000', 'about' => 'أرض سكنية على شارعين في موقع مميز',
            'plot' => ['north_type' => 'street', 'north_width' => '20', 'north_length' => '25', 'south_type' => 'neighbour', 'south_length' => '25',
                'east_type' => 'build', 'east_plotno' => '59', 'east_length' => '25', 'west_type' => 'path', 'west_width' => '8', 'west_length' => '25']]]]);
        InboxMessage::create(['source' => 'unit_inquiry', 'unit_id' => $units[0]->id, 'name' => 'أحمد السالم', 'mobile' => '0559876543',
            'message' => 'السلام عليكم، هل الشقة متاحة للمعاينة يوم الخميس؟']);
        Date::setTestNow();
    }

    /** A simple generated façade picture so the demo needs no external images. */
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
