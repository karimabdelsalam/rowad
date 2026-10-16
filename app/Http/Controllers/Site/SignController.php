<?php

namespace App\Http\Controllers\Site;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\ContractSignature;
use App\Services\Documents\DocumentData;
use App\Services\Signing\ContractSigning;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The page a party opens from their signing link: review the contract, confirm with a code, sign. */
class SignController extends Controller
{
    public function __construct(private readonly ContractSigning $signing) {}

    public function show(string $token, DocumentData $documents): View
    {
        $signature = $this->signature($token);
        $contract = $signature->signable->load(['unit.owners', 'clients']);

        return view('site.sign', [
            'token' => $token,
            'signature' => $signature,
            'contract' => $contract,
            'document' => $documents->rentContract($contract),
            'parties' => $contract->signatures()->orderBy('id')->get(),
        ]);
    }

    public function code(string $token): RedirectResponse
    {
        return $this->attempt($token, fn (ContractSignature $s) => $this->signing->sendCode($s),
            __('أرسلنا رمز التحقق إلى جوالك المنتهي بـ :m', ['m' => substr((string) preg_replace('/\D/', '', (string) $this->signature($token)->mobile), -4)]), 'code_sent');
    }

    public function sign(Request $request, string $token): RedirectResponse
    {
        $request->validate(['code' => ['required', 'digits:6'], 'signature' => ['required', 'string'], 'agree' => ['accepted']], [], [
            'code' => __('رمز التحقق'), 'signature' => __('التوقيع'), 'agree' => __('الإقرار'),
        ]);

        return $this->attempt($token, fn (ContractSignature $s) => $this->signing->sign($s, (string) $request->input('code'),
            (string) $request->input('signature'), $request->ip(), $request->userAgent()), __('تم توقيع العقد بنجاح'));
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:250']], [], ['reason' => __('السبب')]);

        return $this->attempt($token, fn (ContractSignature $s) => $this->signing->decline($s, (string) $request->input('reason')), __('أبلغنا المكتب برفضك وسيتواصل معك'));
    }

    private function attempt(string $token, callable $action, string $success, string $flag = 'done'): RedirectResponse
    {
        try {
            $action($this->signature($token));
        } catch (BusinessRuleException $e) {
            return back()->withInput()->withErrors(['sign' => $e->getMessage()]);
        }

        return redirect()->to('/sign/'.$token)->with('status', $success)->with($flag, true);
    }

    private function signature(string $token): ContractSignature
    {
        return $this->signing->find($token) ?? abort(404);
    }
}
