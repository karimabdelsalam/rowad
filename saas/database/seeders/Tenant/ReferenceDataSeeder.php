<?php

namespace Database\Seeders\Tenant;

use App\Models\City;
use App\Models\Lookups\Bank;
use App\Models\Lookups\DeedType;
use App\Models\Lookups\ExpenseType;
use App\Models\Lookups\MeterType;
use App\Models\Lookups\PropertyCategory;
use App\Models\Lookups\TransactionType;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        // System ledger entry kinds used by the owner-ledger engine (legacy transactions.typeid).
        foreach ([
            ['payment', 'حصة المالك من المبلغ المحصل', true],
            ['tax', 'ضريبة القيمة المضافة', false],
            ['commission', 'عمولة المكتب على المالك', false],
            ['commission2', 'عمولة المكتب على المستأجر/المشتري', true],
            ['comp_tax', 'ضريبة دخل المكتب', false],
            ['owner_tax', 'ضريبة على المالك', false],
            ['mancost', 'رسوم إدارة العقار', false],
            ['outgoings', 'مصروفات على العقار', false],
            ['payout', 'تحويل مستحقات للمالك', false],
        ] as [$code, $title, $income]) {
            TransactionType::updateOrCreate(['code' => $code], ['title' => $title, 'is_income' => $income, 'is_system' => true]);
        }

        $this->titles(PropertyCategory::class, ['شقة', 'فيلا', 'دور', 'دوبلكس', 'استوديو', 'محل تجاري', 'مكتب', 'مستودع', 'أرض', 'عمارة', 'استراحة']);
        $this->titles(DeedType::class, ['صك إلكتروني', 'صك ورقي', 'حجة استحكام', 'وثيقة تملك']);
        $this->titles(MeterType::class, ['كهرباء', 'مياه', 'غاز']);
        // legacy building_borders defaults; attributes: L length, W width, N neighbouring plot number
        foreach ([['street', 'LW', 'شارع'], ['blocked', 'LW', 'شارع سد'], ['path', 'LW', 'ممر'], ['neighbour', 'L', 'جار'], ['facility', 'L', 'مرفق'],
            ['build', 'NL', 'قطعة'], ['lobby', 'L', 'بهو'], ['farm', 'L', 'مزرعة'], ['rest', 'L', 'استراحة'], ['gbuilding', 'NL', 'مبنى حكومي']] as [$code, $attrs, $title]) {
            \App\Models\Lookups\BorderType::firstOrCreate(['code' => $code], ['title' => $title, 'attributes' => str_split($attrs)]);
        }
        $this->titles(ExpenseType::class, ['صيانة', 'كهرباء', 'مياه', 'نظافة', 'حراسة', 'رواتب', 'إيجار مكتب', 'تسويق', 'رسوم حكومية', 'أخرى']);
        $this->titles(Bank::class, [
            'مصرف الراجحي', 'البنك الأهلي السعودي', 'بنك الرياض', 'البنك السعودي البريطاني (ساب)', 'مصرف الإنماء',
            'بنك البلاد', 'بنك الجزيرة', 'البنك السعودي الفرنسي', 'البنك العربي الوطني', 'البنك السعودي للاستثمار',
            'بنك الخليج الدولي',
        ]);

        foreach (['الرياض', 'جدة', 'مكة المكرمة', 'المدينة المنورة', 'الدمام', 'الخبر', 'الظهران', 'الطائف', 'تبوك',
            'بريدة', 'أبها', 'خميس مشيط', 'حائل', 'جازان', 'نجران', 'الجبيل', 'الأحساء', 'ينبع', 'القطيف', 'الباحة'] as $city) {
            City::firstOrCreate(['name' => $city], ['country_code' => 'SA']);
        }

        foreach ($this->defaultSettings() as $group => $values) {
            foreach ($values as $key => $value) {
                Setting::firstOrCreate(['key' => $key], ['group' => $group, 'value' => $value]);
            }
        }
    }

    /** Legacy `setting` keys (docs/feature-inventory.md §6), grouped. */
    private function defaultSettings(): array
    {
        return [
            'identity' => [
                'site_name' => tenant('name') ?? 'مكتب عقاري', 'site_name_en' => null, 'logo' => null,
                'address1' => null, 'address2' => null, 'phone' => null, 'mobile' => null, 'fax' => null,
                'email' => tenant('email'), 'vat_number' => null, 'commercial_register' => null, 'billing_info' => null,
            ],
            'localization' => [
                'currency' => 'SAR', 'country' => 'SA', 'phone_code' => '966', 'timezone' => 'Asia/Riyadh',
                'system_calendar' => 'gregorian', 'salary_calendar' => 'gregorian', 'locale' => 'ar',
            ],
            'taxes' => [
                'vat_percent' => 15,
                'residential_tax_enabled' => false, 'residential_tax_percent' => 0,   // habit_tax
                'commercial_tax_enabled' => false, 'commercial_tax_percent' => 0,     // comm_tax
                'income_tax_enabled' => false, 'income_tax_percent' => 0,             // income_tax
                'owner_tax_enabled' => false, 'owner_tax_percent' => 0,               // owner_tax
            ],
            'mail' => [
                'mail_driver' => 'platform', 'smtp_host' => null, 'smtp_port' => 587,
                'smtp_username' => null, 'smtp_password' => null,
            ],
            'sms' => [
                'sms_provider' => null, 'sms_username' => null, 'sms_password' => null, 'sms_sender' => null,
                'sms_login_staff' => false, 'sms_login_clients' => false,
            ],
            'website' => [
                'landing_enabled' => true,
                'landing_hero_title' => null, 'landing_hero_title_en' => null,
                'landing_hero_subtitle' => null, 'landing_hero_subtitle_en' => null,
                'landing_about' => null, 'landing_about_en' => null,
                'landing_whatsapp' => null, 'landing_primary_color' => '#0f766e',
            ],
            'general' => [
                'support_ticket_prefix' => 'TCK-', 'google_maps_key' => null,
                'site_closed' => false, 'site_closed_message' => null, 'skip_guide_widget' => false,
            ],
        ];
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $model */
    private function titles(string $model, array $titles): void
    {
        foreach ($titles as $title) {
            $model::firstOrCreate(['title' => $title]);
        }
    }
}
