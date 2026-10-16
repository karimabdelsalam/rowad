<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\OnlinePayment;
use App\Services\Payments\Payments;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The public pay page of an installment, the gateway's redirect back, and its webhook. */
class PayController extends Controller
{
    public function show(string $token): View
    {
        $payment = $this->payment($token);
        $installment = $payment->installment->load(['unit', 'contract.clients']);

        return view('site.pay', [
            'payment' => $payment,
            'installment' => $installment,
            'client' => $installment->contract?->clients?->first(),
            'publishableKey' => (string) Settings::get('moyasar_publishable_key'),
            'methods' => Payments::methods(),
            'callback' => url('/pay/'.$payment->token.'/callback'),
            'amountMinor' => (int) round((float) $payment->amount * 100),
            'result' => session('pay_result'),
        ]);
    }

    /** Moyasar sends the payer back with ?id=…&status=…; the server asks the gateway before believing it. */
    public function callback(Request $request, string $token, Payments $payments): RedirectResponse
    {
        $payment = $this->payment($token);
        if (filled($request->query('id'))) {
            $payment = $payments->confirm($payment, (string) $request->query('id'));
        }

        return redirect()->to('/pay/'.$token)->with('pay_result', $payment->isPaid() ? 'paid' : 'failed');
    }

    /** Moyasar webhook (payment_paid / payment_failed): the secret token must match the office's setting. */
    public function webhook(Request $request, Payments $payments): JsonResponse
    {
        $secret = (string) Settings::get('moyasar_webhook_secret');
        if ($secret === '' || ! hash_equals($secret, (string) $request->input('secret_token'))) {
            abort(403);
        }
        $data = (array) $request->input('data', []);
        $token = (string) ($data['metadata']['token'] ?? '');
        $payment = $token !== '' ? OnlinePayment::where('token', $token)->first() : null;
        if ($payment === null || blank($data['id'] ?? null)) {
            return response()->json(['ok' => false, 'reason' => 'unknown payment'], 202);
        }
        $payment = $payments->confirm($payment, (string) $data['id']);

        return response()->json(['ok' => true, 'status' => $payment->status]);
    }

    private function payment(string $token): OnlinePayment
    {
        abort_unless(Payments::enabled() && preg_match('/^[A-Za-z0-9]{20,48}$/', $token), 404);

        return OnlinePayment::where('token', $token)->whereIn('status', [OnlinePayment::STATUS_INITIATED, OnlinePayment::STATUS_PAID, OnlinePayment::STATUS_FAILED])->firstOrFail();
    }
}
