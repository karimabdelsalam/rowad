<?php

namespace App\Services\Messaging;

use App\Enums\ContractStatus;
use App\Enums\MessageChannel;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendOutgoingMessage;
use App\Models\Campaign;
use App\Models\Client;
use App\Models\Employee;
use App\Models\NewsletterSubscriber;
use App\Models\Owner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Legacy newsletter module: bulk email/SMS to a user group or the mailing list. */
class CampaignService
{
    public const AUDIENCES = ['owners', 'clients', 'renters', 'buyers', 'employees', 'subscribers'];

    /** @return Collection<int, array{name: ?string, email: ?string, mobile: ?string}> */
    public function recipients(string $audience): Collection
    {
        $people = match ($audience) {
            'owners' => Owner::query()->get(['full_name', 'email', 'mobile']),
            'clients' => Client::query()->get(['full_name', 'email', 'mobile']),
            'renters' => Client::whereHas('rentContracts', fn ($q) => $q->where('status', ContractStatus::Active))->get(['full_name', 'email', 'mobile']),
            'buyers' => Client::whereHas('saleContracts')->get(['full_name', 'email', 'mobile']),
            'employees' => Employee::query()->get(['full_name', 'email', 'mobile']),
            'subscribers' => NewsletterSubscriber::query()->get(['name as full_name', 'email', 'mobile']),
            default => throw new BusinessRuleException(__('الجمهور غير معروف')),
        };

        return $people->map(fn ($p) => ['name' => $p->full_name, 'email' => $p->email, 'mobile' => $p->mobile]);
    }

    /** Queue one message per recipient with a usable address for the campaign's channel. */
    public function send(Campaign $campaign): int
    {
        if (in_array($campaign->status, ['sending', 'sent'], true)) {
            throw new BusinessRuleException(__('تم إرسال هذه الحملة من قبل'));
        }

        if ($campaign->channel === MessageChannel::WhatsApp && ! WhatsAppGateway::enabled()) {
            throw new BusinessRuleException(__('اربط واتساب للأعمال من الإعدادات أولًا.'));
        }
        $field = $campaign->channel === MessageChannel::Mail ? 'email' : 'mobile';
        $targets = $this->recipients($campaign->audience)->pluck($field)->filter()->map(fn ($v) => trim($v))->unique()->values();
        if ($targets->isEmpty()) {
            throw new BusinessRuleException(__('لا يوجد مستلمون لديهم :field', ['field' => $field === 'mobile' ? __('الجوال') : __('البريد الإلكتروني')]));
        }

        $ids = DB::transaction(function () use ($campaign, $targets) {
            $campaign->update(['status' => 'sending', 'recipients_count' => $targets->count(), 'sent_count' => 0, 'failed_count' => 0]);

            return $targets->map(fn (string $to) => $campaign->messages()->create([
                'channel' => $campaign->channel,
                'event' => 'campaign',
                'recipient' => $to,
                'subject' => $campaign->subject,
                'body' => $campaign->body,
                'status' => 'pending',
            ])->id)->all();
        });

        foreach ($ids as $id) {
            SendOutgoingMessage::dispatch($id);
        }
        $campaign->update(['status' => 'sent']);

        return count($ids);
    }
}
