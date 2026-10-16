<?php

namespace App\Services\Ejar;

use App\Enums\ClientKind;
use App\Models\Client;
use App\Models\Owner;
use App\Models\RentContract;
use App\Models\Unit;
use App\Support\Settings;

/**
 * Everything the Ejar platform asks for when documenting a rent contract, gathered from the office's own
 * records: the brokerage office, every lessor and lessee (Ejar verifies each with their ID and date of
 * birth), the property with its deed and electricity meter, and the contract terms. Staff copy it into
 * Ejar field by field instead of retyping; required fields that are still empty are the checklist.
 */
final class EjarSheet
{
    /** @return list<array{title: string, rows: list<array{label: string, value: ?string, required: bool}>}> */
    public static function for(RentContract $contract): array
    {
        $contract->loadMissing(['clients', 'unit.owners', 'unit.meters.meterType', 'unit.deeds', 'unit.deedType', 'unit.city', 'unit.district',
            'unit.building.city', 'unit.building.district']);
        $unit = $contract->unit;

        return [
            ['title' => __('مكتب الوساطة'), 'rows' => [
                self::row('اسم المنشأة', Settings::get('site_name', tenant('name'))),
                self::row('رقم السجل التجاري', Settings::get('commercial_register')),
                self::row('رقم رخصة فال (الوساطة العقارية)', Settings::get('fal_license_number')),
            ]],
            ...$unit->owners->values()->map(fn (Owner $owner, int $i) => [
                'title' => __('المؤجر').($unit->owners->count() > 1 ? ' '.($i + 1).' ('.rtrim(rtrim((string) $owner->pivot->share_percent, '0'), '.').'%)' : ''),
                'rows' => self::person($owner),
            ])->all(),
            ...$contract->clients->values()->map(fn (Client $client, int $i) => [
                'title' => __('المستأجر').($contract->clients->count() > 1 ? ' '.($i + 1) : ''),
                'rows' => $client->kind === ClientKind::Company ? [
                    self::row('اسم المنشأة', $client->company_name ?: $client->full_name),
                    self::row('رقم السجل التجاري', $client->company_registration_number),
                    self::row('جوال المنشأة', $client->company_mobile ?: $client->mobile),
                    self::row('الرقم الضريبي', $client->vat_number, false),
                ] : self::person($client),
            ])->all(),
            ['title' => __('العقار والوحدة'), 'rows' => self::property($unit, $contract)],
            ['title' => __('العقد'), 'rows' => [
                self::row('نوع العقد', $contract->usage?->getLabel() ?? __('سكني')),
                self::row('تاريخ البداية', $contract->start_date?->format('Y-m-d')),
                self::row('تاريخ النهاية', $contract->end_date?->format('Y-m-d')),
                self::row('الإيجار السنوي', self::money((float) $contract->yearly_rent > 0 ? $contract->yearly_rent : self::yearly($contract))),
                self::row('دورة السداد', $contract->payment_cycle?->getLabel()),
                self::row('قيمة الدفعة', self::money($contract->rent_amount)),
                self::row('عمولة المكتب', (float) $contract->commission > 0 ? self::money($contract->commission) : null, false),
            ]],
        ];
    }

    /** @return list<string> "section: field" for every required field that is still empty */
    public static function missing(RentContract $contract): array
    {
        return collect(self::for($contract))->flatMap(fn (array $group) => collect($group['rows'])
            ->filter(fn (array $row) => $row['required'] && blank($row['value']))
            ->map(fn (array $row) => $group['title'].': '.$row['label']))->values()->all();
    }

    /** Share of required fields already filled, 0–100. */
    public static function readiness(RentContract $contract): int
    {
        $rows = collect(self::for($contract))->flatMap(fn (array $group) => $group['rows'])->where('required', true);

        return $rows->isEmpty() ? 100 : (int) floor($rows->filter(fn (array $row) => filled($row['value']))->count() / $rows->count() * 100);
    }

    /** @return list<array{label: string, value: ?string, required: bool}> */
    private static function person(Owner|Client $person): array
    {
        return [
            self::row('الاسم الكامل', $person->full_name),
            self::row('نوع الهوية', $person->id_type?->getLabel()),
            self::row('رقم الهوية', $person->id_number),
            self::row('تاريخ الميلاد', $person->birth_date?->format('Y-m-d')),
            self::row('الجوال', $person->mobile),
            self::row('الجنسية', $person->nationality, false),
        ];
    }

    /** @return list<array{label: string, value: ?string, required: bool}> */
    private static function property(Unit $unit, RentContract $contract): array
    {
        $deed = $unit->deeds->first();
        $electricity = $unit->meters->first(fn ($meter) => str_contains((string) $meter->meterType?->title, 'كهرباء'));
        $water = $unit->meters->first(fn ($meter) => str_contains((string) $meter->meterType?->title, 'مياه'));

        return [
            self::row('رقم الصك', $unit->deed_number ?: $deed?->number),
            self::row('نوع الصك', $unit->deedType?->title, false),
            self::row('تاريخ الصك', $deed?->issue_date, false),
            self::row('المدينة', $unit->city?->name ?? $unit->building?->city?->name),
            self::row('الحي', $unit->district?->name ?? $unit->building?->district?->name),
            self::row('الشارع', $unit->street ?: $unit->building?->street, false),
            self::row('اسم العقار', $unit->building?->title, false),
            self::row('رقم الوحدة', $unit->number ?: $unit->title),
            self::row('الطابق', $unit->floor, false),
            self::row('المساحة (م²)', (float) $unit->area > 0 ? rtrim(rtrim((string) $unit->area, '0'), '.') : null),
            self::row('عدد الغرف', $unit->rooms ? (string) $unit->rooms : null, false),
            self::row('مؤثثة', $unit->is_furnished ? __('نعم') : __('لا'), false),
            self::row('رقم عداد الكهرباء', $electricity?->number, $contract->usage?->value !== 'commercial'),
            self::row('رقم عداد المياه', $water?->number, false),
        ];
    }

    private static function yearly(RentContract $contract): float
    {
        $months = $contract->payment_cycle?->months() ?: 0;

        return $months > 0 ? (float) $contract->rent_amount * 12 / $months : (float) $contract->rent_amount;
    }

    private static function money(mixed $amount): ?string
    {
        return $amount === null ? null : number_format((float) $amount, 2);
    }

    /** @return array{label: string, value: ?string, required: bool} */
    private static function row(string $label, mixed $value, bool $required = true): array
    {
        return ['label' => __($label), 'value' => filled($value) ? (string) $value : null, 'required' => $required];
    }
}
