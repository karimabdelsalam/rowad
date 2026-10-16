<?php

namespace App\Services\Signing;

use App\Enums\ClientKind;
use App\Enums\NotificationEvent as E;
use App\Exceptions\BusinessRuleException;
use App\Models\Client;
use App\Models\ContractSignature;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\User;
use App\Services\Messaging\Notifier;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * Electronic signature of a rent contract by every owner and tenant. Each party gets a private link by
 * SMS/WhatsApp/email, reviews the contract, receives a one-time code on their own mobile and signs with
 * their finger. Signing stores the drawing, time, IP, device and a fingerprint of the contract terms;
 * when the last party signs, the contract is marked signed and the office is told.
 */
class ContractSigning
{
    public const LINK_DAYS = 14;

    public const OTP_MINUTES = 10;

    public const MAX_OTP_ATTEMPTS = 5;

    /** Largest accepted drawing (a phone canvas PNG is ~10–60 KB). */
    public const MAX_SIGNATURE_BYTES = 400_000;

    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Send (or re-send) the signing links to every party who has not signed yet.
     *
     * @return list<ContractSignature> the pending signatures the links went to
     */
    public function request(RentContract $contract): array
    {
        $contract->loadMissing(['unit.owners', 'clients']);
        $parties = [
            ...$contract->unit->owners->map(fn (Owner $o) => ['lessor', $o, $o->full_name, $o->mobile]),
            ...$contract->clients->map(fn (Client $c) => ['lessee', $c, $c->displayName(), $c->kind === ClientKind::Company ? ($c->company_mobile ?: $c->mobile) : $c->mobile]),
        ];
        if ($noMobile = collect($parties)->filter(fn ($p) => blank($p[3]))->map(fn ($p) => $p[2])->all()) {
            throw new BusinessRuleException(__('أضف رقم الجوال لـ: :names', ['names' => implode('، ', $noMobile)]));
        }

        $sent = [];
        foreach ($parties as [$role, $person, $name, $mobile]) {
            $existing = ContractSignature::query()->whereMorphedTo('signable', $contract)->whereMorphedTo('signer', $person)->latest('id')->first();
            if ($existing?->isSigned() && $existing->document_hash === self::fingerprint($contract)) {
                continue;
            }
            $token = Str::random(48);
            $signature = $existing && ! $existing->isSigned() ? $existing : new ContractSignature;
            $signature->forceFill([
                'signable_type' => $contract->getMorphClass(), 'signable_id' => $contract->id,
                'signer_type' => $person->getMorphClass(), 'signer_id' => $person->id,
                'role' => $role, 'name' => $name, 'mobile' => $mobile,
                'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(self::LINK_DAYS),
                'status' => ContractSignature::PENDING, 'signature' => null, 'signed_at' => null, 'document_hash' => null,
                'otp_hash' => null, 'otp_expires_at' => null, 'otp_attempts' => 0, 'decline_reason' => null,
                'requested_by' => auth()->id(),
            ])->save();

            $this->notifier->send(E::SignatureRequest, [
                'contract_number' => $contract->number,
                'unit' => $contract->unit->title,
                'sign_url' => self::url($token),
            ], [['name' => $name, 'mobile' => $mobile, 'email' => $person->email ?? null]]);
            $sent[] = $signature;
        }
        $contract->forceFill(['signed_at' => null])->save();

        return $sent;
    }

    /** A fresh link for one party (e.g. the tenant pressing "sign" in their portal). */
    public function linkFor(ContractSignature $signature): string
    {
        if ($signature->isSigned()) {
            throw new BusinessRuleException(__('تم توقيع العقد مسبقًا'));
        }
        $token = Str::random(48);
        $signature->forceFill(['token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(self::LINK_DAYS)])->save();

        return self::url($token);
    }

    public static function url(string $token): string
    {
        return url('/sign/'.$token);
    }

    public function find(string $token): ?ContractSignature
    {
        return strlen($token) === 48 ? ContractSignature::query()->where('token_hash', hash('sha256', $token))->first() : null;
    }

    public function sendCode(ContractSignature $signature): void
    {
        $this->ensureOpen($signature);
        if ($signature->otp_expires_at && $signature->otp_expires_at->copy()->subMinutes(self::OTP_MINUTES - 1)->isFuture()) {
            throw new BusinessRuleException(__('انتظر دقيقة قبل طلب رمز جديد'));
        }
        $code = (string) random_int(100000, 999999);
        $signature->forceFill(['otp_hash' => Hash::make($code), 'otp_expires_at' => now()->addMinutes(self::OTP_MINUTES), 'otp_attempts' => 0])->save();

        $this->notifier->send(E::SignatureOtp, ['code' => $code, 'contract_number' => $signature->signable?->number],
            [['name' => $signature->name, 'mobile' => $signature->mobile, 'email' => null]], force: true);
    }

    public function sign(ContractSignature $signature, #[SensitiveParameter] string $code, string $drawing, ?string $ip = null, ?string $userAgent = null): ContractSignature
    {
        $this->ensureOpen($signature);
        $this->checkCode($signature, $code);
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $drawing, $m)
            || strlen($m[1]) > self::MAX_SIGNATURE_BYTES || ! str_starts_with((string) base64_decode($m[1], true), "\x89PNG")) {
            throw new BusinessRuleException(__('ارسم توقيعك في المربع'));
        }

        DB::transaction(function () use ($signature, $drawing, $ip, $userAgent) {
            $contract = $signature->signable;
            $signature->forceFill([
                'status' => ContractSignature::SIGNED, 'signature' => $drawing, 'signed_at' => now(),
                'ip' => $ip, 'user_agent' => Str::limit((string) $userAgent, 250, ''),
                'document_hash' => self::fingerprint($contract), 'otp_hash' => null, 'otp_expires_at' => null,
            ])->save();

            $all = ContractSignature::query()->whereMorphedTo('signable', $contract)->get();
            if ($all->isNotEmpty() && $all->every(fn (ContractSignature $s) => $s->isSigned())) {
                $contract->forceFill(['signed_at' => now()])->save();
                $this->alertStaff(__('اكتمل توقيع العقد :number إلكترونيًا', ['number' => $contract->number]), $contract->unit?->title);
            }
        });

        return $signature;
    }

    public function decline(ContractSignature $signature, string $reason): ContractSignature
    {
        $this->ensureOpen($signature);
        $signature->forceFill(['status' => ContractSignature::DECLINED, 'decline_reason' => Str::limit($reason, 250, '')])->save();
        $this->alertStaff(__(':name رفض توقيع العقد :number', ['name' => $signature->name, 'number' => $signature->signable?->number]), $reason);

        return $signature;
    }

    /**
     * What the parties agree to: parties, unit, dates, amounts, schedule and terms. Re-requesting signatures
     * after any of these change asks everyone again, and the print shows which terms were signed.
     */
    public static function fingerprint(RentContract $contract): string
    {
        $contract->loadMissing(['unit.owners', 'clients', 'terms']);

        return hash('sha256', json_encode([
            $contract->number, $contract->unit_id, $contract->start_date?->toDateString(), $contract->end_date?->toDateString(),
            (string) $contract->rent_amount, $contract->payment_cycle?->value, (string) $contract->vat_amount,
            $contract->unit->owners->map(fn ($o) => [$o->id, (string) $o->pivot->share_percent])->sortBy(0)->values()->all(),
            $contract->clients->pluck('id')->sort()->values()->all(),
            $contract->terms->pluck('id')->sort()->values()->all(), (string) $contract->extra_terms,
            $contract->installments()->orderBy('due_date')->get(['due_date', 'amount'])->map(fn ($i) => [$i->due_date?->toDateString(), (string) $i->amount])->all(),
        ], JSON_UNESCAPED_UNICODE));
    }

    private function ensureOpen(ContractSignature $signature): void
    {
        if ($signature->isSigned()) {
            throw new BusinessRuleException(__('تم توقيع العقد مسبقًا'));
        }
        if ($signature->status === ContractSignature::DECLINED || $signature->expires_at->isPast()) {
            throw new BusinessRuleException(__('انتهت صلاحية رابط التوقيع، اطلب من المكتب رابطًا جديدًا'));
        }
    }

    private function checkCode(ContractSignature $signature, #[SensitiveParameter] string $code): void
    {
        if ($signature->otp_hash === null || $signature->otp_expires_at?->isPast()) {
            throw new BusinessRuleException(__('اطلب رمز التحقق أولًا'));
        }
        if ($signature->otp_attempts >= self::MAX_OTP_ATTEMPTS) {
            throw new BusinessRuleException(__('تجاوزت عدد المحاولات، اطلب رمزًا جديدًا'));
        }
        if (! Hash::check(trim($code), $signature->otp_hash)) {
            $signature->increment('otp_attempts');
            throw new BusinessRuleException(__('رمز التحقق غير صحيح'));
        }
    }

    private function alertStaff(string $title, ?string $body): void
    {
        $staff = User::query()->where('type', 'staff')->where('is_active', true)->get()
            ->filter(fn (User $u) => $u->is_super_admin || $u->can('rent_contracts.update'));
        if ($staff->isNotEmpty()) {
            FilamentNotification::make()->title($title)->body($body)->icon('heroicon-o-pencil-square')->info()->sendToDatabase($staff);
        }
    }
}
