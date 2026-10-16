<?php

namespace App\Mail;

use App\Jobs\SendOutgoingMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the platform team an office just signed up so someone can reach out. */
class NewOfficeAlert extends Mailable
{
    use Queueable, SerializesModels;

    /** @param array{name: string, subdomain: string, url: string, plan: ?string, admin_name: string, admin_email: string, phone: ?string, trial_ends_at: ?string, console_url: ?string} $office */
    public function __construct(public array $office) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('مؤسسة جديدة سجّلت: :name', ['name' => $this->office['name']]));
    }

    public function content(): Content
    {
        $o = $this->office;
        $rows = [
            [__('المؤسسة'), e($o['name'])],
            [__('الرابط'), '<a href="'.e($o['url']).'" style="color:#0f766e;direction:ltr;unicode-bidi:embed">'.e($o['url']).'</a>'],
            [__('الباقة'), e($o['plan'] ?? '—')],
            [__('المسؤول'), e($o['admin_name']).' — <span style="direction:ltr;unicode-bidi:embed">'.e($o['admin_email']).'</span>'],
            [__('الجوال'), '<span style="direction:ltr;unicode-bidi:embed">'.e($o['phone'] ?? '—').'</span>'],
            [__('نهاية التجربة'), '<span style="direction:ltr;unicode-bidi:embed">'.e($o['trial_ends_at'] ?? '—').'</span>'],
        ];
        $body = '<table role="presentation" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;width:100%;font-size:14px;">';
        foreach ($rows as [$label, $value]) {
            $body .= '<tr><td style="padding:9px 14px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap">'.e($label).'</td><td style="padding:9px 14px;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f172a">'.$value.'</td></tr>';
        }
        $body .= '</table>';
        if (filled($o['console_url'] ?? null)) {
            $body .= '<p style="margin-top:16px"><a href="'.e($o['console_url']).'" style="color:#0f766e;font-weight:700">'.e(__('فتح المؤسسة في لوحة المنصة')).'</a></p>';
        }

        return new Content(htmlString: SendOutgoingMessage::layout($this->envelope()->subject, $body));
    }
}
