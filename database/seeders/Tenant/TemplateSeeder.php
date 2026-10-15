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

    /** A label/value box for the key figures of an email (inline styles: email clients ignore stylesheets). */
    public static function details(array $rows): string
    {
        $cells = collect($rows)->map(fn ($value, $label) => '<tr><td style="padding:10px 14px;color:#64748b;font-size:13px;width:38%;border-bottom:1px solid #e2e8f0;">'.$label
            .'</td><td style="padding:10px 14px;font-weight:700;color:#0f172a;border-bottom:1px solid #e2e8f0;unicode-bidi:plaintext;">'.$value.'</td></tr>')->join('');

        return '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;margin:16px 0;border:1px solid #e2e8f0;border-radius:12px;border-collapse:separate;background:#f8fafc;overflow:hidden;">'.$cells.'</table>';
    }

    /** event => [email subject, email body (HTML), sms body|null] */
    public function messages(): array
    {
        return [
            E::RentDueIn15Days->value => ['تذكير باستحقاق قسط الإيجار', '<p>عزيزي {name}،</p><p>نذكّركم بقرب استحقاق قسط الإيجار التالي:</p>'.self::details(['الوحدة' => '{unit}', 'المبلغ' => '{amount}', 'تاريخ الاستحقاق' => '{due_date}']).'<p>يمكنكم متابعة دفعاتكم من بوابة العملاء.</p>', 'عزيزي {name}، يستحق قسط إيجار {unit} بمبلغ {amount} بتاريخ {due_date}.'],
            E::SaleInstallmentDueIn15Days->value => ['تذكير باستحقاق قسط', '<p>عزيزي {name}،</p><p>نذكّركم بقرب استحقاق قسط عقد الشراء:</p>'.self::details(['الوحدة' => '{unit}', 'المبلغ' => '{amount}', 'تاريخ الاستحقاق' => '{due_date}']), 'عزيزي {name}، يستحق قسط {unit} بمبلغ {amount} بتاريخ {due_date}.'],
            E::SaleInstallmentDueTomorrow->value => ['قسط مستحق غدًا', '<p>عزيزي {name}،</p><p>يستحق غدًا القسط التالي:</p>'.self::details(['الوحدة' => '{unit}', 'المبلغ' => '{amount}']), 'تذكير: يستحق غدًا قسط {unit} بمبلغ {amount}.'],
            E::SaleInstallmentDueToday->value => ['قسط مستحق اليوم', '<p>عزيزي {name}،</p><p>يستحق اليوم القسط التالي:</p>'.self::details(['الوحدة' => '{unit}', 'المبلغ' => '{amount}']), 'تذكير: يستحق اليوم قسط {unit} بمبلغ {amount}.'],
            E::SaleInstallmentOverdue->value => ['قسط متأخر السداد', '<p>عزيزي {name}،</p><p>نفيدكم بتأخر سداد القسط التالي، ونأمل المبادرة بالسداد:</p>'.self::details(['الوحدة' => '{unit}', 'المبلغ' => '{amount}', 'تاريخ الاستحقاق' => '{due_date}']), 'تنبيه: قسط {unit} بمبلغ {amount} متأخر منذ {due_date}.'],
            E::SaleInstallmentCollected->value => ['تم استلام الدفعة', '<p>عزيزي {name}،</p><p>تم استلام دفعتكم، شكرًا لكم.</p>'.self::details(['الوحدة' => '{unit}', 'المبلغ المستلم' => '{amount}']), 'تم استلام {amount} عن {unit}. شكرًا لكم.'],
            E::ContractEndedOwner->value => ['انتهاء عقد إيجار', '<p>عزيزي {name}، انتهى عقد إيجار وحدتكم {unit} ({location}) مع المستأجر {renters}.</p>', 'انتهى عقد إيجار وحدتكم {unit} مع {renters}.'],
            E::ContractEndedRenter->value => ['انتهاء عقد الإيجار', '<p>عزيزي {name}،</p><p>نود إعلامكم بقرب انتهاء عقد الإيجار:</p>'.self::details(['الوحدة' => '{unit}', 'تاريخ الانتهاء' => '{end_date}']).'<p>للتجديد يرجى التواصل معنا.</p>', 'عزيزي {name}، ينتهي عقد إيجار {unit} بتاريخ {end_date}.'],
            E::OwnerUnitRented->value => ['تم تأجير وحدتكم', '<p>عزيزي {name}،</p><p>يسعدنا إبلاغكم بتأجير وحدتكم:</p>'.self::details(['الوحدة' => '{unit}', 'المستأجر' => '{renters}', 'المدة' => '{start_date} — {end_date}', 'القيمة' => '{amount}']), 'تم تأجير {unit} إلى {renters} بقيمة {amount}.'],
            E::OwnerPayout->value => ['تحويل مستحقات', '<p>عزيزي {name}،</p><p>تم تحويل مستحقاتكم:</p>'.self::details(['المبلغ' => '{amount}', 'التاريخ' => '{date}', 'رقم السند' => '{voucher_number}']).'<p>تجدون كشف الحساب كاملًا في بوابة الملاك.</p>', 'تم تحويل {amount} لحسابكم بتاريخ {date}.'],
            E::NewRentContract->value => ['عقد إيجار جديد', '<p>عزيزي {name}،</p><p>تم تسجيل عقد الإيجار التالي:</p>'.self::details(['رقم العقد' => '{contract_number}', 'الوحدة' => '{unit}', 'من' => '{start_date}', 'إلى' => '{end_date}']), 'تم تسجيل عقد إيجار {unit} رقم {contract_number}.'],
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
