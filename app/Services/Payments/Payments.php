<?php

namespace App\Services\Payments;

use App\Models\Client;
use App\Models\Installment;
use App\Models\OnlinePayment;
use App\Services\Finance\VoucherService;
use App\Support\PlanLimits;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * E-collection: a pay link per open installment; when the gateway confirms the money, the installment is
 * collected and a receipt voucher is issued exactly as a cashier would — no manual matching.
 */
class Payments
{
    public const METHODS = ['creditcard' => 'بطاقات مدى والائتمان', 'applepay' => 'Apple Pay', 'stcpay' => 'STC Pay'];

    public function __construct(private readonly PaymentGateway $gateway, private readonly VoucherService $vouchers) {}

    /** Gateway configured by the office and allowed by its plan. */
    public static function enabled(): bool
    {
        return tenancy()->initialized
            && Settings::get('payments_gateway') === 'moyasar'
            && filled(Settings::get('moyasar_publishable_key')) && filled(Settings::get('moyasar_secret_key'))
            && PlanLimits::allows('online_payments');
    }

    /** @return list<string> the methods the office turned on (Moyasar form names) */
    public static function methods(): array
    {
        $chosen = Settings::list('payments_methods', array_keys(self::METHODS)) ?: array_keys(self::METHODS);

        return array_values(array_intersect(array_keys(self::METHODS), $chosen)) ?: ['creditcard'];
    }

    /** The current pay link for an open installment (one per remaining amount), or null when e-collection is off. */
    public function linkFor(Installment $installment): ?OnlinePayment
    {
        if (! self::enabled() || $installment->is_collected || $installment->remainingAmount() <= 0 || $installment->type->value === 'expenses') {
            return null;
        }
        $amount = $installment->remainingAmount();
        $open = $installment->onlinePayments()->where('status', OnlinePayment::STATUS_INITIATED)->latest('id')->first();
        if ($open && (float) $open->amount === $amount) {
            return $open;
        }
        $open?->update(['status' => OnlinePayment::STATUS_SUPERSEDED]);

        return $installment->onlinePayments()->create([
            'token' => Str::random(40),
            'amount' => $amount,
            'currency' => 'SAR',
            'gateway' => $this->gateway->name(),
        ]);
    }

    public function urlFor(Installment $installment): ?string
    {
        return $this->linkFor($installment)?->url();
    }

    /**
     * The gateway's verdict for a link. Idempotent: a second callback or a webhook after the redirect
     * finds the payment already paid and changes nothing.
     */
    public function confirm(OnlinePayment $payment, string $gatewayId): OnlinePayment
    {
        if ($payment->isPaid()) {
            return $payment;
        }
        if (($taken = OnlinePayment::where('gateway_id', $gatewayId)->whereKeyNot($payment->id)->first()) !== null) {
            return $taken; // the same gateway payment cannot settle two links
        }

        try {
            $remote = $this->gateway->fetch($gatewayId);
        } catch (Throwable $e) {
            report($e);
            $payment->update(['failure' => mb_substr($e->getMessage(), 0, 500)]);

            return $payment;
        }

        return DB::transaction(function () use ($payment, $remote) {
            /** @var OnlinePayment $payment */
            $payment = OnlinePayment::lockForUpdate()->findOrFail($payment->id);
            if ($payment->isPaid()) {
                return $payment;
            }
            $expectedMinor = (int) round((float) $payment->amount * 100);
            $fields = ['gateway_id' => $remote->id, 'source' => $remote->source, 'payload' => $remote->raw];

            if (! $remote->isPaid()) {
                $payment->update($fields + ['status' => $remote->status === 'initiated' ? OnlinePayment::STATUS_INITIATED : OnlinePayment::STATUS_FAILED, 'failure' => $remote->failure]);

                return $payment;
            }
            if ($remote->amountMinor !== $expectedMinor || $remote->currency !== $payment->currency) {
                $payment->update($fields + ['status' => OnlinePayment::STATUS_FAILED,
                    'failure' => sprintf('amount mismatch: gateway %d %s, link %d %s', $remote->amountMinor, $remote->currency, $expectedMinor, $payment->currency)]);

                return $payment;
            }

            $installment = $payment->installment()->with(['unit', 'contract.clients'])->lockForUpdate()->firstOrFail();
            $voucher = null;
            if (! $installment->is_collected) {
                $client = $installment->contract?->clients?->first();
                $voucher = $this->vouchers->createReceipt([
                    'issued_at' => today(),
                    'amount' => $installment->remainingAmount(),
                    'payment_method' => 'card',
                    'bank_account_id' => Settings::get('payments_bank_account_id') ?: null,
                    'party_type' => $client instanceof Client ? 'client' : null,
                    'party_id' => $client?->id,
                    'unit_id' => $installment->unit_id,
                    'reason' => __('سداد إلكتروني عبر :gateway (:id)', ['gateway' => ucfirst($payment->gateway), 'id' => $remote->id]),
                ], [$installment->id]);
            }
            $payment->update($fields + ['status' => OnlinePayment::STATUS_PAID, 'paid_at' => now(), 'receipt_voucher_id' => $voucher?->id, 'failure' => null]);

            return $payment;
        });
    }
}
