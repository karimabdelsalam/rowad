<?php
declare(strict_types=1);

/**
 * إرسال الرسائل التلقائية (واتساب / SMS / بريد).
 *
 * لا يوجد مزوّد واحد يناسب كل العيادات، لذلك الإرسال يمرّ عبر مزوّد قابل للضبط
 * من الإعدادات:
 *
 *  - webhook  : يرسل JSON إلى أي رابط (يعمل مع بوابات واتساب المحلية أو
 *               أدوات الأتمتة مثل n8n / Make / Zapier).
 *  - meta     : واتساب الرسمي (WhatsApp Cloud API من Meta).
 *  - email    : بريد إلكتروني عبر دالة mail() المتاحة على cPanel.
 *  - manual   : بدون إرسال تلقائي — تُسجَّل الرسالة فقط لإرسالها يدويًا.
 *
 * كل محاولة تُسجَّل في جدول message_log سواء نجحت أو فشلت.
 */

/** @return array{ok:bool,error:string} */
function notify_send(PDO $pdo, ?int $patientId, string $target, string $body, string $refType = '', ?int $refId = null): array
{
    $channel = setting('notify_channel', 'whatsapp');

    if (setting('notify_enabled', '0') !== '1') {
        notify_log($pdo, $patientId, $channel, $target, $body, 'skipped', 'الإرسال التلقائي غير مفعّل', $refType, $refId);
        return ['ok' => false, 'error' => 'الإرسال التلقائي غير مفعّل'];
    }
    if ($target === '') {
        notify_log($pdo, $patientId, $channel, $target, $body, 'skipped', 'لا يوجد رقم أو بريد صالح', $refType, $refId);
        return ['ok' => false, 'error' => 'لا يوجد رقم أو بريد صالح'];
    }

    $provider = setting('notify_provider', 'webhook');
    $result = match ($provider) {
        'meta'   => notify_via_meta($target, $body),
        'email'  => notify_via_email($target, $body),
        'manual' => ['ok' => false, 'error' => 'وضع الإرسال اليدوي — الرسالة مسجّلة فقط'],
        default  => notify_via_webhook($target, $body, $channel),
    };

    notify_log(
        $pdo, $patientId, $channel, $target, $body,
        $result['ok'] ? 'sent' : ($provider === 'manual' ? 'skipped' : 'failed'),
        $result['error'], $refType, $refId
    );
    return $result;
}

function notify_log(PDO $pdo, ?int $patientId, string $channel, string $target, string $body,
                    string $status, string $error, string $refType, ?int $refId): void
{
    $pdo->prepare(
        'INSERT INTO message_log (patient_id, channel, target, body, status, error, ref_type, ref_id)
         VALUES (?,?,?,?,?,?,?,?)'
    )->execute([$patientId, $channel, $target, $body, $status, mb_substr($error, 0, 250), $refType, $refId]);
}

/** هل أُرسلت رسالة لهذا المرجع من قبل؟ يمنع تكرار التذكير عند تشغيل الكرون أكثر من مرة */
function notify_already_sent(PDO $pdo, string $refType, int $refId, string $sinceDate): bool
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM message_log
         WHERE ref_type = ? AND ref_id = ? AND status = 'sent' AND created_at >= ?"
    );
    $st->execute([$refType, $refId, $sinceDate . ' 00:00:00']);
    return (int)$st->fetchColumn() > 0;
}

/* ------------------------------------------------------------- المزوّدون */

/** POST بصيغة JSON إلى رابط تحدده أنت — الأكثر مرونة */
function notify_via_webhook(string $target, string $body, string $channel): array
{
    $url = setting('notify_url');
    if ($url === '') {
        return ['ok' => false, 'error' => 'لم يتم ضبط رابط الإرسال في الإعدادات'];
    }
    $payload = [
        'to'      => $target,
        'message' => $body,
        'channel' => $channel,
        'sender'  => setting('notify_sender'),
    ];
    $headers = ['Content-Type: application/json'];
    if (setting('notify_token') !== '') {
        $headers[] = 'Authorization: Bearer ' . setting('notify_token');
    }
    return http_post_json($url, $payload, $headers);
}

/** واتساب الرسمي من Meta — يتطلب قالبًا معتمدًا للرسائل المبتدأة من العيادة */
function notify_via_meta(string $target, string $body): array
{
    $phoneId = setting('notify_sender');
    $token = setting('notify_token');
    if ($phoneId === '' || $token === '') {
        return ['ok' => false, 'error' => 'أدخل Phone Number ID ورمز الوصول في الإعدادات'];
    }
    $url = 'https://graph.facebook.com/v21.0/' . rawurlencode($phoneId) . '/messages';
    $payload = [
        'messaging_product' => 'whatsapp',
        'to'                => $target,
        'type'              => 'text',
        'text'              => ['preview_url' => false, 'body' => $body],
    ];
    return http_post_json($url, $payload, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
    ]);
}

function notify_via_email(string $target, string $body): array
{
    if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'بريد إلكتروني غير صالح'];
    }
    $subject = '=?UTF-8?B?' . base64_encode('تذكير من ' . setting('clinic_name', 'العيادة')) . '?=';
    $headers = "MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    $from = setting('notify_sender');
    if ($from !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $headers .= 'From: ' . $from . "\r\n";
    }
    return @mail($target, $subject, $body, $headers)
        ? ['ok' => true, 'error' => '']
        : ['ok' => false, 'error' => 'فشل إرسال البريد من السيرفر'];
}

/** @return array{ok:bool,error:string} */
function http_post_json(string $url, array $payload, array $headers): array
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'error' => 'رابط الإرسال غير صالح'];
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $response = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => $err ?: 'تعذر الاتصال بالمزوّد'];
        }
        return $code >= 200 && $code < 300
            ? ['ok' => true, 'error' => '']
            : ['ok' => false, 'error' => "استجابة $code: " . mb_substr((string)$response, 0, 180)];
    }

    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $json,
        'timeout'       => 20,
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return ['ok' => false, 'error' => 'تعذر الاتصال بالمزوّد'];
    }
    $code = 0;
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
            $code = (int)$m[1];
        }
    }
    return $code >= 200 && $code < 300
        ? ['ok' => true, 'error' => '']
        : ['ok' => false, 'error' => "استجابة $code"];
}
