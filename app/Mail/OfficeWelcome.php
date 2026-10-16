<?php

namespace App\Mail;

use App\Jobs\SendOutgoingMessage;
use App\Support\PlatformSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Sent to the person who just created an office: where to log in, how long the trial lasts, what to do first. */
class OfficeWelcome extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{name: string, url: string, email: string, trial_ends_at: ?string, plan: ?string} $office */
    public function __construct(public array $office, public string $adminName) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('مرحبًا بك في عقار نوفا — حساب :name جاهز', ['name' => $this->office['name']]));
    }

    public function content(): Content
    {
        $o = $this->office;
        $steps = [
            __('أكمل بيانات المؤسسة وارفع شعارها من الإعدادات، فيظهر في البوابات والمطبوعات.'),
            __('أضف عقاراتك ووحداتك وملاكها.'),
            __('سجّل أول عقد إيجار؛ يبني النظام جدول الدفعات تلقائيًا.'),
            __('فعّل الرسائل النصية ليصل التذكير إلى المستأجرين قبل الاستحقاق.'),
            __('شارك روابط بوابة الملاك وبوابة المستأجرين من لوحة التحكم.'),
        ];
        $body = '<p>'.e(__('أهلًا :name،', ['name' => $this->adminName])).'</p>';
        $body .= '<p>'.e(__('أُنشئ حساب مؤسستك ":office" وهو جاهز للعمل الآن.', ['office' => $o['name']])).'</p>';
        $body .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:14px 0;border:1px solid #e2e8f0;border-radius:12px;width:100%;font-size:14px;">';
        $rows = [
            [__('رابط الدخول'), '<a href="'.e($o['url']).'/admin" style="color:#0f766e;font-weight:700;direction:ltr;unicode-bidi:embed">'.e($o['url']).'/admin</a>'],
            [__('البريد الإلكتروني'), '<span style="direction:ltr;unicode-bidi:embed">'.e($o['email']).'</span>'],
        ];
        if (filled($o['plan'] ?? null)) {
            $rows[] = [__('الباقة'), e($o['plan'])];
        }
        if (filled($o['trial_ends_at'] ?? null)) {
            $rows[] = [__('نهاية الفترة التجريبية'), '<span style="direction:ltr;unicode-bidi:embed">'.e($o['trial_ends_at']).'</span>'];
        }
        foreach ($rows as [$label, $value]) {
            $body .= '<tr><td style="padding:9px 14px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap">'.e($label).'</td><td style="padding:9px 14px;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f172a">'.$value.'</td></tr>';
        }
        $body .= '</table>';
        $body .= '<p style="margin:18px 0 6px"><a href="'.e($o['url']).'/admin" style="display:inline-block;background:#0f766e;color:#fff;font-weight:800;padding:11px 22px;border-radius:12px;text-decoration:none">'.e(__('الدخول إلى لوحة الإدارة')).'</a></p>';
        $body .= '<p style="margin-top:22px;font-weight:800;color:#0f172a">'.e(__('خطواتك الأولى')).'</p><ol style="padding-inline-start:20px;margin:0">';
        foreach ($steps as $step) {
            $body .= '<li style="margin:4px 0">'.e($step).'</li>';
        }
        $body .= '</ol>';
        $contact = array_filter([PlatformSettings::contact('email'), PlatformSettings::contact('phone')]);
        if ($contact) {
            $body .= '<p style="margin-top:22px;color:#64748b">'.e(__('نساعدك في نقل بياناتك من نظامك السابق وفي أي سؤال:')).' <span style="direction:ltr;unicode-bidi:embed">'.e(implode(' · ', $contact)).'</span></p>';
        }

        return new Content(htmlString: SendOutgoingMessage::layout($this->envelope()->subject, $body));
    }
}
