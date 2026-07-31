<?php
declare(strict_types=1);

/**
 * إرسال رسائل واتساب من الكونسول (رمز التحقق OTP وغيره).
 *
 * روابط wa.me المجانية لا تصلح هنا لأنها تفتح محادثة ولا تُرسل شيئًا بنفسها؛
 * الإرسال الفعلي يحتاج مزوّدًا:
 *   - meta:    واتساب الرسمي (Cloud API) — يحتاج قالب رسالة معتمدًا من ميتا
 *              من نوع Authentication قبل الاستخدام.
 *   - webhook: أي بوابة واتساب تقبل POST بسيطًا (UltraMsg وأشباهها أو
 *              سكربت وسيط عندك) — الأسهل للبدء.
 *   - off:     لا إرسال، وخطوة OTP في التسجيل تُتخطى تلقائيًا.
 */

/** هل إرسال الواتساب مضبوط وجاهز؟ */
function wa_enabled(): bool
{
    return match (setting('wa_provider', 'off')) {
        'meta'    => setting('wa_meta_token') !== '' && setting('wa_meta_phone_id') !== '',
        'webhook' => setting('wa_webhook_url') !== '',
        default   => false,
    };
}

/** يطبّع رقم الهاتف إلى صيغة دولية أرقام فقط (201001234567) */
function wa_normalize_phone(string $phone): ?string
{
    $cc = preg_replace('/\D/', '', setting('country_code', '20')) ?: '20';
    $p = preg_replace('/\D/', '', $phone);
    if ($p === '') {
        return null;
    }
    if (str_starts_with($p, '00')) {
        $p = substr($p, 2);
    } elseif (str_starts_with($p, '0')) {
        $p = $cc . substr($p, 1);
    } elseif (!str_starts_with($p, $cc)) {
        $p = $cc . $p;
    }
    return strlen($p) >= 10 && strlen($p) <= 15 ? $p : null;
}

/**
 * يرسل رسالة واتساب نصية.
 *
 * @return array{ok: bool, error: string}
 */
function wa_send(string $phone, string $text): array
{
    $to = wa_normalize_phone($phone);
    if (!$to) {
        return ['ok' => false, 'error' => 'رقم هاتف غير صالح'];
    }

    $provider = setting('wa_provider', 'off');
    if ($provider === 'meta') {
        return wa_http(
            'https://graph.facebook.com/v19.0/' . rawurlencode(setting('wa_meta_phone_id')) . '/messages',
            ['Authorization: Bearer ' . setting('wa_meta_token'), 'Content-Type: application/json'],
            json_encode([
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $text],
            ], JSON_UNESCAPED_UNICODE)
        );
    }

    if ($provider === 'webhook') {
        $headers = ['Content-Type: application/json'];
        if (setting('wa_webhook_token') !== '') {
            $headers[] = 'Authorization: Bearer ' . setting('wa_webhook_token');
        }
        return wa_http(
            setting('wa_webhook_url'),
            $headers,
            json_encode(['phone' => $to, 'message' => $text], JSON_UNESCAPED_UNICODE)
        );
    }

    return ['ok' => false, 'error' => 'الإرسال غير مفعّل'];
}

/** @return array{ok: bool, error: string} */
function wa_http(string $url, array $headers, string $body): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false) {
        return ['ok' => false, 'error' => 'تعذر الاتصال: ' . $err];
    }
    if ($code >= 400) {
        return ['ok' => false, 'error' => 'رفض المزوّد الإرسال (HTTP ' . $code . ')'];
    }
    return ['ok' => true, 'error' => ''];
}

/* ------------------------------------------------------------- رمز التحقق */

const OTP_TTL       = 300;  // صلاحية الرمز: 5 دقائق
const OTP_RESEND    = 60;   // أقل فاصل بين إرسالين
const OTP_MAX_TRIES = 5;    // محاولات إدخال خاطئة قبل الإلغاء

/**
 * يولّد رمزًا ويرسله على واتساب، ويحفظ تجزئته في الجلسة.
 *
 * @return array{ok: bool, error: string}
 */
function otp_send(string $phone): array
{
    $prev = $_SESSION['otp'] ?? null;
    if ($prev && time() - (int)$prev['sent_at'] < OTP_RESEND) {
        return ['ok' => false, 'error' => 'انتظر ' . (OTP_RESEND - (time() - (int)$prev['sent_at'])) . ' ثانية قبل إعادة الإرسال.'];
    }

    $code = (string)random_int(100000, 999999);
    $brand = setting('brand_name', 'نظام العيادات');
    $res = wa_send($phone, "رمز التحقق: $code\nصالح 5 دقائق — $brand");
    if (!$res['ok']) {
        return $res;
    }

    $_SESSION['otp'] = [
        'hash'    => hash('sha256', $code),
        'phone'   => $phone,
        'sent_at' => time(),
        'tries'   => 0,
    ];
    return ['ok' => true, 'error' => ''];
}

/**
 * يتحقق من الرمز المُدخل ويستهلكه عند النجاح.
 *
 * @return array{ok: bool, error: string}
 */
function otp_verify(string $input, string $phone): array
{
    $otp = $_SESSION['otp'] ?? null;
    if (!$otp || $otp['phone'] !== $phone) {
        return ['ok' => false, 'error' => 'اطلب رمزًا جديدًا.'];
    }
    if (time() - (int)$otp['sent_at'] > OTP_TTL) {
        unset($_SESSION['otp']);
        return ['ok' => false, 'error' => 'انتهت صلاحية الرمز — اطلب رمزًا جديدًا.'];
    }
    if ((int)$otp['tries'] >= OTP_MAX_TRIES) {
        unset($_SESSION['otp']);
        return ['ok' => false, 'error' => 'محاولات كثيرة خاطئة — اطلب رمزًا جديدًا.'];
    }

    $_SESSION['otp']['tries'] = (int)$otp['tries'] + 1;

    if (!hash_equals($otp['hash'], hash('sha256', preg_replace('/\D/', '', $input) ?? ''))) {
        return ['ok' => false, 'error' => 'الرمز غير صحيح.'];
    }

    unset($_SESSION['otp']);
    return ['ok' => true, 'error' => ''];
}
