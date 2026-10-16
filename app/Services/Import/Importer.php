<?php

namespace App\Services\Import;

use App\Support\Hijri;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * One kind of record to import. Columns are matched by their Arabic header (as in the template); each row is
 * validated, then created or matched to an existing record so importing the same file twice does not duplicate.
 */
abstract class Importer
{
    abstract public static function key(): string;

    abstract public static function title(): string;

    /** @return array<string, array{0: string, 1: bool, 2: string}> field => [header, required, example] */
    abstract public static function columns(): array;

    /** @return list<string> problems with the row (empty = ready to import) */
    abstract public function problems(array $row): array;

    /** @return 'created'|'updated'|'skipped' */
    abstract public function import(array $row, array $options): string;

    /** Map a header-keyed row to field-keyed values. */
    public static function fields(array $row): array
    {
        $out = [];
        foreach (static::columns() as $field => [$header]) {
            $value = $row[__($header)] ?? $row[$header] ?? '';
            $out[$field] = $value instanceof DateTimeInterface ? $value : trim((string) $value);
        }

        return $out;
    }

    /** @return list<string> the template's header row */
    public static function headers(): array
    {
        return array_values(array_map(fn ($c) => __($c[0]).($c[1] ? ' *' : ''), static::columns()));
    }

    /** @return list<string> */
    public static function example(): array
    {
        return array_values(array_map(fn ($c) => $c[2], static::columns()));
    }

    /** @return list<string> required columns missing from the row */
    protected static function missing(array $fields): array
    {
        return collect(static::columns())->filter(fn ($c, $field) => $c[1] && blank($fields[$field] ?? null))
            ->map(fn ($c) => __('العمود «:column» مطلوب', ['column' => __($c[0])]))->values()->all();
    }

    /** Match an enum by its value or its (Arabic or English) label. @param class-string<BackedEnum> $enum */
    protected static function enum(string $enum, ?string $value): ?BackedEnum
    {
        if (blank($value)) {
            return null;
        }
        $needle = mb_strtolower(trim($value));
        foreach ($enum::cases() as $case) {
            $labels = [$case->value, method_exists($case, 'getLabel') ? $case->getLabel() : $case->name];
            if (collect($labels)->contains(fn ($l) => mb_strtolower(trim((string) $l)) === $needle)) {
                return $case;
            }
        }

        return null;
    }

    /** Excel date, 2026-01-31, 31/01/2026, or a Hijri date (14xx-…). */
    protected static function date(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->startOfDay();
        }
        $value = self::digits((string) $value);
        if ($value === '') {
            return null;
        }
        try {
            if (preg_match('/^(1[34]\d\d)[\/-](\d{1,2})[\/-](\d{1,2})$/', $value)) {
                return Hijri::parse($value);
            }
            if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{4})$/', $value, $m)) {
                return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1]);
            }
            if (is_numeric($value) && (float) $value > 20000 && (float) $value < 80000) { // Excel serial date
                return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value);
            }

            return CarbonImmutable::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function number(mixed $value): ?float
    {
        $value = str_replace([',', '٬', ' '], '', self::digits((string) $value));
        $value = str_replace('٫', '.', $value);

        return is_numeric($value) ? (float) $value : null;
    }

    /** Arabic-Indic digits → Western digits. */
    protected static function digits(string $value): string
    {
        return trim(strtr($value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
    }

    /** Mobile as digits with a leading zero (05…) when a Saudi number is given without it. */
    protected static function mobile(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', self::digits((string) $value));
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '966')) {
            $digits = '0'.substr($digits, 3);
        } elseif (strlen($digits) === 9 && str_starts_with($digits, '5')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }
}
