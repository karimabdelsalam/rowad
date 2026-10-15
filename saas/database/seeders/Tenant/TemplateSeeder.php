<?php

namespace Database\Seeders\Tenant;

use App\Enums\NotificationEvent as E;
use App\Models\MessageTemplate;
use App\Models\PrintTemplate;
use Illuminate\Database\Seeder;

class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->messages() as $event => [$subject, $body, $sms]) {
            $case = E::from($event);
            MessageTemplate::firstOrCreate(['event' => $event], [
                'title' => $case->getLabel(),
                'email_subject' => $subject,
                'email_body' => $body,
                'sms_body' => $sms,
                'email_enabled' => true,
                'sms_enabled' => $sms !== null,
            ]);
        }

        foreach (self::printTemplates() as $key => $template) {
            PrintTemplate::firstOrCreate(['key' => $key], $template);
        }
    }

    /** event => [email subject, email body (HTML), sms body|null] */
    private function messages(): array
    {
        return [
            E::RentDueIn15Days->value => ['تذكير باستحقاق قسط الإيجار', '<p>عزيزي {name}،</p><p>نذكركم باستحقاق قسط إيجار الوحدة {unit} بمبلغ {amount} بتاريخ {due_date}.</p>', 'عزيزي {name}، يستحق قسط إيجار {unit} بمبلغ {amount} بتاريخ {due_date}.'],
            E::SaleInstallmentDueIn15Days->value => ['تذكير باستحقاق قسط', '<p>عزيزي {name}،</p><p>يستحق قسط عقد الشراء للوحدة {unit} بمبلغ {amount} بتاريخ {due_date}.</p>', 'عزيزي {name}، يستحق قسط {unit} بمبلغ {amount} بتاريخ {due_date}.'],
            E::SaleInstallmentDueTomorrow->value => ['قسط مستحق غدًا', '<p>عزيزي {name}، يستحق غدًا قسط الوحدة {unit} بمبلغ {amount}.</p>', 'تذكير: يستحق غدًا قسط {unit} بمبلغ {amount}.'],
            E::SaleInstallmentDueToday->value => ['قسط مستحق اليوم', '<p>عزيزي {name}، يستحق اليوم قسط الوحدة {unit} بمبلغ {amount}.</p>', 'تذكير: يستحق اليوم قسط {unit} بمبلغ {amount}.'],
            E::SaleInstallmentOverdue->value => ['قسط متأخر السداد', '<p>عزيزي {name}، نفيدكم بتأخر سداد قسط الوحدة {unit} بمبلغ {amount} المستحق بتاريخ {due_date}.</p>', 'تنبيه: قسط {unit} بمبلغ {amount} متأخر منذ {due_date}.'],
            E::SaleInstallmentCollected->value => ['تم استلام الدفعة', '<p>عزيزي {name}، تم استلام مبلغ {amount} عن الوحدة {unit}. شكرًا لكم.</p>', 'تم استلام {amount} عن {unit}. شكرًا لكم.'],
            E::ContractEndedOwner->value => ['انتهاء عقد إيجار', '<p>عزيزي {name}، انتهى عقد إيجار وحدتكم {unit} ({location}) مع المستأجر {renters}.</p>', 'انتهى عقد إيجار وحدتكم {unit} مع {renters}.'],
            E::ContractEndedRenter->value => ['انتهاء عقد الإيجار', '<p>عزيزي {name}، ينتهي عقد إيجار الوحدة {unit} بتاريخ {end_date}.</p>', 'عزيزي {name}، ينتهي عقد إيجار {unit} بتاريخ {end_date}.'],
            E::OwnerUnitRented->value => ['تم تأجير وحدتكم', '<p>عزيزي {name}، تم تأجير الوحدة {unit} إلى {renters} من {start_date} إلى {end_date} بقيمة {amount}.</p>', 'تم تأجير {unit} إلى {renters} بقيمة {amount}.'],
            E::OwnerPayout->value => ['تحويل مستحقات', '<p>عزيزي {name}، تم تحويل مبلغ {amount} لحسابكم بتاريخ {date} بموجب السند رقم {voucher_number}.</p>', 'تم تحويل {amount} لحسابكم بتاريخ {date}.'],
            E::NewRentContract->value => ['عقد إيجار جديد', '<p>عزيزي {name}، تم تسجيل عقد إيجار الوحدة {unit} رقم {contract_number} من {start_date} إلى {end_date}.</p>', 'تم تسجيل عقد إيجار {unit} رقم {contract_number}.'],
            E::EmployeeIdExpiring->value => ['قرب انتهاء إقامة موظف', '<p>تنتهي إقامة/هوية الموظف {name} رقم {visa_number} بتاريخ {expire_date}.</p>', 'تنتهي إقامة {name} رقم {visa_number} بتاريخ {expire_date}.'],
            E::LicenseExpiring->value => ['قرب انتهاء رخصة', '<p>تنتهي الرخصة {license} بتاريخ {expire_date}.</p>', 'تنتهي الرخصة {license} بتاريخ {expire_date}.'],
            E::PasswordResetOtp->value => ['رمز استعادة كلمة المرور', '<p>رمز استعادة كلمة المرور: <strong>{code}</strong></p>', 'رمز استعادة كلمة المرور: {code}'],
            E::LoginOtp->value => ['رمز الدخول', '<p>رمز الدخول الخاص بك: <strong>{code}</strong></p>', 'رمز الدخول: {code}'],
            E::AccountPasswordReset->value => ['بيانات الدخول', '<p>عزيزي {name}، بيانات دخولك: اسم المستخدم {username} وكلمة المرور {password}. رابط الدخول: {login_url}</p>', 'بيانات دخولك: {username} / {password}'],
            E::PasswordRecovery->value => ['استعادة كلمة المرور', '<p>عزيزي {name}، لاستعادة كلمة المرور اضغط على الرابط: <a href="{reset_url}">{reset_url}</a></p>', null],
            E::EmailChanged->value => ['تم تغيير البريد الإلكتروني', '<p>عزيزي {name}، تم تغيير بريدك الإلكتروني إلى {email}.</p>', null],
            E::NewRegistration->value => ['مرحبًا بك', '<p>عزيزي {name}، تم إنشاء حسابك باسم المستخدم {username}. رابط الدخول: {login_url}</p>', null],
        ];
    }

    public const PRINT_TITLES = [
        'rent_contract' => 'عقد إيجار', 'sale_contract' => 'عقد بيع', 'receipt_voucher' => 'سند قبض', 'payment_voucher' => 'سند صرف',
        'invoice' => 'فاتورة', 'tax_invoice' => 'فاتورة ضريبية', 'owner_statement' => 'كشف حساب مالك', 'letter' => 'خطاب رسمي',
        'letter_cancel' => 'إخلاء طرف', 'letter_rentraise' => 'إشعار بتغيير الإيجار', 'letter_finish' => 'تسليم عقار', 'letter_review' => 'تقييم عقار',
    ];

    /**
     * Default document designs live in resources/print-templates/<key>.html (styled by print/document.blade.php).
     * Placeholders follow the legacy processDoc() conventions ({org_*}, {$}, {serial}, {date}, blocks <!--{owners}-->).
     *
     * @return array<string, array{title: string, header: string, content: string, footer: string}>
     */
    public static function printTemplates(): array
    {
        $dir = resource_path('print-templates');
        $header = trim((string) file_get_contents("{$dir}/_header.html"));
        $footer = trim((string) file_get_contents("{$dir}/_footer.html"));

        return collect(self::PRINT_TITLES)->mapWithKeys(fn (string $title, string $key) => [$key => [
            'title' => $title, 'header' => $header, 'footer' => $footer, 'content' => trim((string) file_get_contents("{$dir}/{$key}.html")),
        ]])->all();
    }
}
