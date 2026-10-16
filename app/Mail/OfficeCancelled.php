<?php

namespace App\Mail;

use App\Jobs\SendOutgoingMessage;
use App\Models\Tenant;
use App\Services\Platform\SubscriptionManager;
use App\Support\PlatformSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Cancellation confirmation (with the one-click way back) and the reminder three days before the data is deleted. */
class OfficeCancelled extends Mailable
{
    public function __construct(public Tenant $tenant, public string $stage) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->stage === 'reminder'
            ? __('تُحذف بيانات :name بعد 3 أيام', ['name' => $this->tenant->name])
            : __('تم إلغاء اشتراك :name — بياناتك محفوظة 30 يومًا', ['name' => $this->tenant->name]));
    }

    public function content(): Content
    {
        $t = $this->tenant;
        $purge = $t->purge_after?->format('Y-m-d');
        $link = SubscriptionManager::reactivationUrl($t);

        $body = $this->stage === 'reminder'
            ? '<p>'.e(__('أُلغي اشتراك ":name" بتاريخ :date، وستُحذف كل بياناتها نهائيًا بتاريخ :purge.', ['name' => $t->name, 'date' => $t->cancelled_at?->format('Y-m-d'), 'purge' => $purge])).'</p>'
            : '<p>'.e(__('أُلغي اشتراك ":name" وتوقف الدخول إلى لوحة الإدارة والبوابات والموقع.', ['name' => $t->name])).'</p>'
              .'<p>'.e(__('بياناتك كلها (الوحدات والعقود والسندات والمستندات) محفوظة حتى :purge. بعد هذا التاريخ تُحذف نهائيًا ولا يمكن استرجاعها.', ['purge' => $purge])).'</p>';

        if ($link) {
            $body .= '<p style="margin:20px 0"><a href="'.e($link).'" style="display:inline-block;background:#0f766e;color:#fff;font-weight:800;padding:11px 22px;border-radius:12px;text-decoration:none">'.e(__('إعادة تفعيل المؤسسة')).'</a></p>';
            $body .= '<p style="color:#64748b;font-size:13px">'.e(__('الرابط صالح حتى :purge ويعيد كل شيء كما كان بضغطة واحدة.', ['purge' => $purge])).'</p>';
        }
        $contact = array_filter([PlatformSettings::contact('email'), PlatformSettings::contact('phone')]);
        if ($contact) {
            $body .= '<p style="margin-top:18px">'.e(__('نرحب برأيك ونسعد بمساعدتك:')).' <span style="direction:ltr;unicode-bidi:embed">'.e(implode(' · ', $contact)).'</span></p>';
        }

        return new Content(htmlString: SendOutgoingMessage::layout($this->envelope()->subject, $body));
    }
}
