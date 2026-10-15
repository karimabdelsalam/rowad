<?php

namespace App\Http\Resources\V1;

use BackedEnum;
use DateTimeInterface;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Http\Resources\Json\JsonResource;

/** Shared formatting: enums as {value, label} in the request locale, dates as Y-m-d, money as strings. */
abstract class ApiResource extends JsonResource
{
    /** @return array{value: string|int, label: string}|null */
    protected function enum(?BackedEnum $enum): ?array
    {
        return $enum === null ? null : ['value' => $enum->value, 'label' => $enum instanceof HasLabel ? (string) $enum->getLabel() : (string) $enum->value];
    }

    protected function date(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    protected function money(mixed $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2, '.', '');
    }
}
