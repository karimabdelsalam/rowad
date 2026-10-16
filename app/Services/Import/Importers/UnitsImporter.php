<?php

namespace App\Services\Import\Importers;

use App\Enums\OfferType;
use App\Enums\PaymentCycle;
use App\Models\Building;
use App\Models\City;
use App\Models\District;
use App\Models\Owner;
use App\Models\Unit;
use App\Services\Import\Importer;

/**
 * Units with their building, location, default rent and owner. The building, city and district are created on
 * first use; the owner is matched by ID number (or created from the name given). A unit is matched by its name
 * within the same building, so importing the file again updates rather than duplicates.
 */
class UnitsImporter extends Importer
{
    public static function key(): string
    {
        return 'units';
    }

    public static function title(): string
    {
        return __('الوحدات');
    }

    public static function columns(): array
    {
        return [
            'title' => ['اسم الوحدة', true, 'شقة 12'],
            'building' => ['العقار / المبنى', false, 'عمارة النخيل'],
            'city' => ['المدينة', false, 'الرياض'],
            'district' => ['الحي', false, 'العليا'],
            'number' => ['رقم الوحدة', false, '12'],
            'floor' => ['الدور', false, '3'],
            'rooms' => ['عدد الغرف', false, '4'],
            'area' => ['المساحة (م²)', false, '140'],
            'offer_type' => ['نوع العرض', false, 'للإيجار'],
            'rent_amount' => ['الإيجار السنوي', false, '36000'],
            'payment_cycle' => ['دورة السداد', false, 'شهري'],
            'deed_number' => ['رقم الصك', false, '310105012345'],
            'owner_id_number' => ['رقم هوية المالك', false, '1012345678'],
            'owner_name' => ['اسم المالك', false, 'سعد محمد العتيبي'],
            'share' => ['نسبة الملكية %', false, '100'],
        ];
    }

    public function problems(array $row): array
    {
        $f = static::fields($row);
        $problems = static::missing($f);
        if (filled($f['offer_type']) && static::enum(OfferType::class, $f['offer_type']) === null) {
            $problems[] = __('نوع العرض «:value» غير معروف (للإيجار / للبيع)', ['value' => $f['offer_type']]);
        }
        if (filled($f['payment_cycle']) && static::enum(PaymentCycle::class, $f['payment_cycle']) === null) {
            $problems[] = __('دورة السداد «:value» غير معروفة', ['value' => $f['payment_cycle']]);
        }
        foreach (['rooms', 'area', 'rent_amount', 'share'] as $field) {
            if (filled($f[$field]) && (static::number($f[$field]) === null || static::number($f[$field]) < 0)) {
                $problems[] = __('قيمة «:column» غير صحيحة', ['column' => __(static::columns()[$field][0])]);
            }
        }
        if (filled($f['share']) && static::number($f['share']) > 100) {
            $problems[] = __('نسبة الملكية أكبر من 100%');
        }
        if (filled($f['owner_id_number']) && blank($f['owner_name']) && OwnersImporter::find(static::digits($f['owner_id_number']), null) === null) {
            $problems[] = __('المالك برقم الهوية :number غير موجود؛ أضف اسمه أو استورد الملاك أولًا', ['number' => static::digits($f['owner_id_number'])]);
        }

        return $problems;
    }

    public function import(array $row, array $options): string
    {
        $f = static::fields($row);
        $city = filled($f['city']) ? City::query()->firstOrCreate(['name' => $f['city']]) : null;
        $district = filled($f['district']) ? District::query()->firstOrCreate(['name' => $f['district'], 'city_id' => $city?->id]) : null;
        $building = filled($f['building']) ? (Building::query()->where('title', $f['building'])->first()
            ?? Building::create(['title' => $f['building'], 'city_id' => $city?->id, 'district_id' => $district?->id])) : null;

        $values = array_filter([
            'title' => $f['title'],
            'building_id' => $building?->id,
            'city_id' => $city?->id ?? $building?->city_id,
            'district_id' => $district?->id ?? $building?->district_id,
            'address' => $district?->name,
            'number' => $f['number'],
            'floor' => $f['floor'],
            'rooms' => filled($f['rooms']) ? (int) static::number($f['rooms']) : null,
            'area' => static::number($f['area']),
            'offer_type' => static::enum(OfferType::class, $f['offer_type'])?->value,
            'rent_amount' => static::number($f['rent_amount']),
            'rent_payment_cycle' => static::enum(PaymentCycle::class, $f['payment_cycle'])?->value,
            'deed_number' => static::digits($f['deed_number']),
        ], fn ($v) => filled($v));

        $unit = Unit::query()->where('title', $f['title'])->where('building_id', $building?->id)->first();
        if ($unit !== null && ! ($options['update_existing'] ?? false)) {
            return 'skipped';
        }
        $result = $unit === null ? 'created' : 'updated';
        $unit ??= new Unit;
        $unit->fill($values)->save();

        if (($owner = $this->owner($f)) !== null) {
            $share = filled($f['share']) ? (float) static::number($f['share']) : 100;
            $unit->owners()->syncWithoutDetaching([$owner->id => ['share_percent' => $share, 'expenses_percent' => $share]]);
        }

        return $result;
    }

    private function owner(array $f): ?Owner
    {
        $idNumber = static::digits($f['owner_id_number']) ?: null;
        $owner = OwnersImporter::find($idNumber, null);
        if ($owner === null && filled($f['owner_name'])) {
            $owner = Owner::query()->where('full_name', trim(preg_replace('/\s+/u', ' ', $f['owner_name'])))->first()
                ?? Owner::create(static::nameParts($f['owner_name']) + array_filter(['id_number' => $idNumber]));
        }

        return $owner;
    }

    /** "سعد محمد عبدالله العتيبي" → first, father, grandfather, family. */
    public static function nameParts(string $name): array
    {
        $parts = preg_split('/\s+/u', trim($name)) ?: [];
        $first = array_shift($parts) ?? '';
        $family = count($parts) > 0 ? array_pop($parts) : null;

        return array_filter(['first_name' => $first, 'father_name' => $parts[0] ?? null,
            'grandfather_name' => isset($parts[1]) ? implode(' ', array_slice($parts, 1)) : null, 'family_name' => $family], fn ($v) => filled($v));
    }
}
