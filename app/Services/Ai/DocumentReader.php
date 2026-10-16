<?php

namespace App\Services\Ai;

use App\Models\AiConversation;
use App\Models\User;
use App\Support\Hijri;
use Illuminate\Support\Str;

/**
 * Reads a photo or scan of an identity document or a title deed and returns the fields for the form, for
 * staff to review before saving. Counts as one assistant question. The image is sent to the AI service
 * and not stored by the system.
 */
class DocumentReader
{
    public const KINDS = ['id', 'deed'];

    private const MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];

    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(private readonly ClaudeClient $client) {}

    /**
     * @param  'id'|'deed'  $kind
     * @return array<string, string> form field => value, only what could be read
     */
    public function read(User $user, string $kind, string $bytes, string $mime): array
    {
        Assistant::ensureAvailable();
        if (! in_array($mime, self::MIMES, true)) {
            throw new AiUnavailable(__('ارفع صورة (JPG / PNG) أو ملف PDF'));
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new AiUnavailable(__('الملف أكبر من 5 ميجابايت'));
        }

        $source = ['type' => 'base64', 'media_type' => $mime, 'data' => base64_encode($bytes)];
        $content = [
            ['type' => $mime === 'application/pdf' ? 'document' : 'image', 'source' => $source],
            ['type' => 'text', 'text' => $kind === 'deed' ? self::DEED_PROMPT : self::ID_PROMPT],
        ];

        $conversation = AiConversation::create(['user_id' => $user->id, 'panel' => 'tools', 'title' => $kind === 'deed' ? __('قراءة صك') : __('قراءة هوية'),
            'context' => ['type' => 'reading', 'kind' => $kind]]);
        $conversation->messages()->create(['role' => 'user', 'content' => '['.$kind.' image]', 'kind' => 'reading']);
        $response = $this->client->message('You read Saudi documents precisely. Never guess: leave a field null when it is not clearly visible.',
            [['role' => 'user', 'content' => $content]], [], 800);
        $text = collect($response['content'] ?? [])->where('type', 'text')->pluck('text')->implode("\n");
        $conversation->messages()->create([
            'role' => 'assistant', 'kind' => 'reading', 'content' => Str::limit($text, 2000),
            'input_tokens' => (int) data_get($response, 'usage.input_tokens', 0),
            'output_tokens' => (int) data_get($response, 'usage.output_tokens', 0),
        ]);

        $json = json_decode(trim((string) preg_replace('/^```(?:json)?|```$/m', '', $text)), true);
        if (! is_array($json)) {
            throw new AiUnavailable(__('تعذّرت قراءة المستند، جرّب صورة أوضح'));
        }

        return $kind === 'deed' ? self::deedFields($json) : self::idFields($json);
    }

    private const ID_PROMPT = <<<'TXT'
        This is a Saudi national ID, iqama (resident permit), GCC ID or passport. Return only JSON:
        {"document_type": "national_id"|"iqama"|"gcc_id"|"passport"|null,
         "first_name": Arabic first name or null, "father_name": ..., "grandfather_name": ..., "family_name": ...,
         "id_number": digits only or null, "nationality": in Arabic or null,
         "birth_date": "YYYY-MM-DD" Gregorian or null, "birth_date_hijri": "YYYY-MM-DD" Hijri or null,
         "expiry_date": "YYYY-MM-DD" Gregorian or null, "expiry_date_hijri": "YYYY-MM-DD" Hijri or null,
         "issue_place": in Arabic or null}
        Split the full Arabic name into its parts in order (first, father, grandfather, family).
        TXT;

    private const DEED_PROMPT = <<<'TXT'
        This is a Saudi real-estate title deed (صك ملكية). Return only JSON:
        {"deed_number": digits or null, "deed_date_hijri": "YYYY-MM-DD" or null, "deed_date": "YYYY-MM-DD" Gregorian or null,
         "issuer": issuing court / notary in Arabic or null, "owner_name": in Arabic or null, "owner_id_number": digits or null,
         "city": in Arabic or null, "district": in Arabic or null, "plan_number": or null, "plot_number": or null, "area": number in m² or null}
        TXT;

    /** @return array<string, string> */
    private static function idFields(array $j): array
    {
        $type = in_array($j['document_type'] ?? null, ['national_id', 'iqama', 'gcc_id', 'passport'], true) ? $j['document_type'] : null;

        return array_filter([
            'first_name' => self::text($j['first_name'] ?? null),
            'father_name' => self::text($j['father_name'] ?? null),
            'grandfather_name' => self::text($j['grandfather_name'] ?? null),
            'family_name' => self::text($j['family_name'] ?? null),
            'id_type' => $type,
            'id_number' => self::digits($j['id_number'] ?? null),
            'nationality' => self::text($j['nationality'] ?? null) ?? ($type === 'national_id' ? 'سعودي' : null),
            'birth_date' => self::date($j['birth_date'] ?? null, $j['birth_date_hijri'] ?? null),
            'id_source' => self::text($j['issue_place'] ?? null),
        ], fn ($v) => filled($v));
    }

    /** @return array<string, string> */
    private static function deedFields(array $j): array
    {
        return array_filter([
            'deed_number' => self::digits($j['deed_number'] ?? null),
            'deed_date' => self::text($j['deed_date_hijri'] ?? null) ?? self::text($j['deed_date'] ?? null),
            'deed_issuer' => self::text($j['issuer'] ?? null),
            'owner_name' => self::text($j['owner_name'] ?? null),
            'plan_number' => self::text($j['plan_number'] ?? null),
            'plot_number' => self::text($j['plot_number'] ?? null),
            'area' => is_numeric($j['area'] ?? null) ? (string) round((float) $j['area'], 2) : null,
            'city' => self::text($j['city'] ?? null),
            'district' => self::text($j['district'] ?? null),
        ], fn ($v) => filled($v));
    }

    private static function text(mixed $v): ?string
    {
        $v = is_scalar($v) ? trim((string) $v) : '';

        return $v === '' || strtolower($v) === 'null' ? null : Str::limit($v, 190, '');
    }

    private static function digits(mixed $v): ?string
    {
        // Arabic-Indic digits on Saudi documents become Western digits
        $v = strtr((string) (is_scalar($v) ? $v : ''), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $digits = preg_replace('/\D/', '', $v);

        return $digits === '' ? null : $digits;
    }

    /** Gregorian date, from the Gregorian value or converted from the Hijri one. */
    private static function date(mixed $gregorian, mixed $hijri): ?string
    {
        foreach ([[$gregorian, false], [$hijri, true]] as [$value, $isHijri]) {
            $value = self::text($value);
            if ($value === null || ! preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value)) {
                continue;
            }
            try {
                $date = $isHijri ? Hijri::parse($value) : \Carbon\CarbonImmutable::parse($value);
            } catch (\Throwable) {
                continue;
            }
            if ($date->year >= 1900 && $date->isPast()) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
