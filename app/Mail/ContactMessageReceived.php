<?php

namespace App\Mail;

use App\Jobs\SendOutgoingMessage;
use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Tells the platform team someone wrote from the official site's contact form; replying answers the sender. */
class ContactMessageReceived extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('رسالة جديدة من الموقع: :topic — :name', ['topic' => $this->contact->topicLabel(), 'name' => $this->contact->name]),
            replyTo: filled($this->contact->email) ? [new Address($this->contact->email, $this->contact->name)] : [],
        );
    }

    public function content(): Content
    {
        $c = $this->contact;
        $ltr = fn (?string $v) => '<span style="direction:ltr;unicode-bidi:embed">'.e($v ?: '—').'</span>';
        $rows = [
            [__('الاسم'), e($c->name)],
            [__('المؤسسة'), e($c->company ?: '—')],
            [__('الجوال'), $ltr($c->phone)],
            [__('البريد الإلكتروني'), $ltr($c->email)],
            [__('المدينة'), e($c->city ?: '—')],
            [__('الموضوع'), e($c->topicLabel())],
        ];
        $body = '<table role="presentation" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:12px;width:100%;font-size:14px;">';
        foreach ($rows as [$label, $value]) {
            $body .= '<tr><td style="padding:9px 14px;color:#64748b;border-bottom:1px solid #f1f5f9;white-space:nowrap">'.e($label).'</td><td style="padding:9px 14px;border-bottom:1px solid #f1f5f9;font-weight:700;color:#0f172a">'.$value.'</td></tr>';
        }
        $body .= '</table><div style="margin-top:16px;padding:14px 16px;background:#f8fafc;border-radius:12px;white-space:pre-line;line-height:1.8;color:#0f172a">'.e($c->message).'</div>';
        $body .= '<p style="margin-top:16px"><a href="'.e(\App\Filament\Platform\Resources\ContactMessages\ContactMessageResource::getUrl('index', panel: 'platform')).'" style="color:#0f766e;font-weight:700">'.e(__('فتح رسائل التواصل في لوحة المنصة')).'</a></p>';

        return new Content(htmlString: SendOutgoingMessage::layout($this->envelope()->subject, $body));
    }
}
