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

        foreach ($this->printTemplates() as $key => [$title, $content]) {
            PrintTemplate::firstOrCreate(['key' => $key], [
                'title' => $title,
                'header' => '<div style="text-align:center"><img src="{org_logo}" height="80"><h2>{org_arname}</h2><p>{org_city} - {org_address} | {org_phone}</p></div>',
                'content' => $content,
                'footer' => '<p style="text-align:center;font-size:12px">{org_email} | {org_mobile}</p>',
            ]);
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

    /** Placeholders follow the legacy processDoc() conventions ({org_*}, {today}, {number}, {date}). */
    private function printTemplates(): array
    {
        return [
            'rent_contract' => ['عقد إيجار', '<h3 style="text-align:center">عقد إيجار رقم {serial}</h3><p>{number} — {doc_date}</p><p>إنه في يوم {today} الموافق {today_date} تم الاتفاق بين المؤجر: {owners} والمستأجر: {renters} على تأجير الوحدة {unit} الكائنة في {location} لمدة {period} تبدأ من {start_date} وتنتهي في {end_date} بقيمة إيجارية {rent_amount} تدفع {payment_cycle}.</p>{terms}<p>{qrcode_owner} {qrcode_renter}</p>'],
            'sale_contract' => ['عقد بيع', '<h3 style="text-align:center">عقد بيع رقم {serial}</h3><p>تم الاتفاق بين البائع: {owners} والمشتري: {buyers} على بيع الوحدة {unit} بمبلغ إجمالي {total_amount} ({payment_type}).</p>{installments_table}{terms}'],
            'receipt_voucher' => ['سند قبض', '<h3 style="text-align:center">سند قبض رقم {serial}</h3><p>{doc_date}</p><p>استلمنا من: {party} مبلغ {amount} ({amount_words}) وذلك عن: {reason} — طريقة الدفع: {payment_method}</p>'],
            'payment_voucher' => ['سند صرف', '<h3 style="text-align:center">سند صرف رقم {serial}</h3><p>{doc_date}</p><p>صرفنا إلى: {party} مبلغ {amount} ({amount_words}) وذلك عن: {reason} — طريقة الدفع: {payment_method}</p>'],
            'invoice' => ['فاتورة', '<h3 style="text-align:center">فاتورة رقم {serial}</h3><p>{doc_date}</p><p>العميل: {customer}</p>{items_table}<p>الإجمالي: {total}</p>'],
            'tax_invoice' => ['فاتورة ضريبية', '<h3 style="text-align:center">فاتورة ضريبية رقم {serial}</h3><p>الرقم الضريبي: {vat_number} — {doc_date}</p><p>العميل: {customer} — الرقم الضريبي للعميل: {customer_vat_number}</p>{items_table}<p>المجموع قبل الضريبة: {subtotal}</p><p>ضريبة القيمة المضافة: {vat_total}</p><p>الإجمالي: {total}</p><div>{qrcode}</div>'],
            'letter' => ['خطاب رسمي', '<p>{doc_date}</p><p>إلى من يهمه الأمر،</p><p>{body}</p>'],
            'owner_statement' => ['كشف حساب مالك', '<h3 style="text-align:center">كشف حساب المالك {owner}</h3><p>من {from_date} إلى {to_date}</p>{transactions_table}<p>الرصيد: {balance}</p>'],
        ];
    }
}
