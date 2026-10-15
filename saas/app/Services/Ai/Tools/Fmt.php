<?php

namespace App\Services\Ai\Tools;

use Illuminate\Support\Carbon;

/** Compact, model-friendly values for tool results. */
final class Fmt
{
    public static function label(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return method_exists($value, 'getLabel') ? (string) $value->getLabel() : (string) $value->value;
        }

        return $value === null ? null : (string) $value;
    }

    public static function date(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : ($value ? Carbon::parse($value)->format('Y-m-d') : null);
    }

    public static function money(mixed $value): float
    {
        return round((float) $value, 2);
    }

    public static function limit(array $input, int $default = 20, int $max = 50): int
    {
        return max(1, min($max, (int) ($input['limit'] ?? $default)));
    }

    public static function period(array $input): array
    {
        $from = isset($input['from']) ? Carbon::parse($input['from'])->startOfDay() : now()->startOfYear();
        $to = isset($input['to']) ? Carbon::parse($input['to'])->endOfDay() : now()->endOfDay();

        return [$from, $to];
    }
}
