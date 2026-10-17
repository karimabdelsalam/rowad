<?php

namespace App\Services\Ejar;

use App\Enums\IdType;
use App\Models\Building;
use App\Models\Owner;
use App\Models\Unit;
use App\Models\UnitMeter;
use App\Support\Hijri;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Registers a property and its units in the office's Ejar portfolio (ECRS "Properties" and "Units" endpoints),
 * so contracts can be sent without the office opening Ejar. A building is one Ejar property; a unit without a
 * building (a villa, a standalone floor) is its own property.
 *
 * What Ejar asks for and the office did not keep before is saved as an "Ejar profile" on the building / unit,
 * prefilled from what the system already knows (deed, owners, city, district, floors, area, rooms, meters), so
 * nothing is typed twice and a failed registration can be retried as is.
 */
final class EjarPortfolio
{
    public function __construct(private EjarApi $api) {}

    public static function make(): self
    {
        return new self(new EjarApi);
    }

    /** The record that stands for the Ejar property: the unit's building, or the unit itself. */
    public static function holder(Unit $unit): Building|Unit
    {
        return $unit->building ?? $unit;
    }

    public static function propertyId(Unit $unit): ?string
    {
        $holder = self::holder($unit);

        return ($holder instanceof Building ? $holder->ejar_property_id : null) ?: ($unit->ejar_property_id ?: null);
    }

    // ------------------------------------------------------------------ form state

    /** @return array<string, mixed> ownership document + property, as the registration form shows them */
    public static function propertyState(Unit $unit): array
    {
        $unit->loadMissing(['building.city', 'building.district', 'city', 'district', 'deeds.deedType', 'deedType']);
        $holder = self::holder($unit);
        $saved = (array) (($holder instanceof Building ? $holder->ejar_profile : $unit->ejar_property) ?? []);
        $deed = $unit->deeds->first();
        $city = $unit->building?->city?->name ?? $unit->city?->name;

        return array_replace_recursive([
            'document_number' => $unit->deed_number ?: $deed?->number,
            'issued_date' => self::gregorian($deed?->issue_date),
            'ownership_document_type' => 'electronic_title_deed',
            'issue_place' => $deed?->source ?: $city,
            'issued_by' => $deed?->source,
            'legal_document_type_name' => $deed?->deedType?->title ?? $unit->deedType?->title,
            'property_name' => $unit->building?->title ?? $unit->title,
            'property_number' => self::digits($unit->building?->number ?? $unit->number, 4),
            'property_type' => $unit->building ? 'building' : 'villa',
            'property_usage' => 'residential_families',
            'region' => self::region($city),
            'city' => $city,
            'district' => $unit->building?->district?->name ?? $unit->district?->name,
            'street_name' => $unit->building?->street ?? $unit->street,
            'building_number' => null,
            'postal_code' => null,
            'additional_number' => null,
            'total_floors' => $unit->building?->floors ?: 1,
            'units_per_floor' => $unit->building ? max(1, (int) ceil($unit->building->units()->count() / max(1, (int) $unit->building->floors))) : 1,
            'established_date' => null,
            'facilities' => array_fill_keys(array_keys(config('ejar.facilities')), 0),
        ], $saved);
    }

    /** @return array<string, mixed> the unit's details, as the registration form shows them */
    public static function unitState(Unit $unit): array
    {
        $unit->loadMissing(['meters.meterType', 'category']);
        $meters = self::meters($unit);
        $rooms = (int) $unit->rooms;

        return array_replace_recursive([
            'unit_number' => $unit->number ?: $unit->title,
            'floor_number' => filled($unit->floor) ? ((string) $unit->floor === '0' ? 'G' : (string) $unit->floor) : 'G',
            'unit_type' => self::unitType($unit),
            'unit_usage' => 'residential_families',
            'area' => (float) $unit->area > 0 ? (float) $unit->area : null,
            'rooms' => $rooms ?: null,
            'is_furnished' => (bool) $unit->is_furnished,
            'furnish_type' => $unit->is_furnished ? 'furnish_used' : null,
            'unit_direction' => 'north',
            'unit_finishing' => 'finished',
            'established_date' => null,
            'include_mezzanine' => false,
            'dimensions' => ['length' => null, 'width' => null, 'height' => null],
            'amenities' => array_replace(array_fill_keys(array_keys(config('ejar.amenities')), 0), ['bedrooms' => $rooms, 'bathrooms_full' => $rooms ? 1 : 0, 'halls' => $rooms ? 1 : 0, 'kitchen' => 1]),
            'fixtures' => ['is_kitchen_sink_installed' => true, 'is_cabinet_installed' => true],
            'electricity_meter' => $meters['electricity'],
            'water_meter' => $meters['water'],
            'gas_meter' => $meters['gas'],
        ], (array) ($unit->ejar_profile ?? []));
    }

    /** Keeps what the office entered, so a retry or the next unit of the building starts from it. */
    public static function remember(Unit $unit, ?array $property, ?array $details): void
    {
        $keep = fn (array $state, array $drop) => Arr::except($state, $drop);
        if ($property !== null) {
            $holder = self::holder($unit);
            $data = $keep($property, ['document_number', 'issued_date', 'scans']); // the deed itself stays on the unit's deed records
            $holder instanceof Building ? $holder->forceFill(['ejar_profile' => $data])->save() : $unit->forceFill(['ejar_property' => $data]);
        }
        if ($details !== null) {
            $unit->forceFill(['ejar_profile' => $details]);
        }
        $unit->save();
    }

    /** @return list<string> what is still missing before the unit can be registered (empty = ready) */
    public static function problems(Unit $unit): array
    {
        $unit->loadMissing('owners');
        $problems = [];
        if (! EjarApi::connected()) {
            $problems[] = __('اربط حساب المكتب بمنصة إيجار من صفحة "منصة إيجار" في الإعدادات.');
        }
        if ($unit->owners->isEmpty()) {
            $problems[] = __('أضف مالك الوحدة أولًا؛ إيجار يسجل العقار باسم مالكه.');
        }
        foreach ($unit->owners as $owner) {
            if (blank($owner->id_number) || ($owner->id_type === IdType::CommercialRegister && $owner->birth_date === null)) {
                $problems[] = __('أكمل هوية المالك :name (رقم الهوية، وتاريخ القيد للسجل التجاري).', ['name' => $owner->full_name]);
            }
        }

        return $problems;
    }

    // ------------------------------------------------------------------ registration

    /**
     * Registers what is missing on Ejar — the property (unless the building already is) and then the unit —
     * and links the unit. Ids are saved after each step, so a failure halfway is resumed, not repeated.
     *
     * @param  list<array{name: string, mime: string, content: string}>  $scans  copies of a non-electronic deed
     */
    public function register(Unit $unit, array $property, array $details, array $scans = []): void
    {
        self::remember($unit, $property, $details);

        $propertyId = self::propertyId($unit);
        if ($propertyId === null) {
            $propertyId = $this->api->createProperty($this->propertyPayload($unit, $property, $scans));
            $this->linkProperty($unit, $propertyId);
        }

        if (blank($unit->ejar_unit_id)) {
            $unitId = $this->api->createUnit($propertyId, self::unitPayload($details));
            $unit->forceFill(['ejar_property_id' => $propertyId, 'ejar_unit_id' => $unitId])->save();
        }
    }

    /** Registers a unit whose property is already on Ejar, from its saved (or default) profile. */
    public function registerUnit(Unit $unit): void
    {
        $propertyId = self::propertyId($unit) ?? throw new EjarException(__('سجّل العقار في إيجار أولًا.'));
        $details = self::unitState($unit);
        if (blank($details['area'] ?? null)) {
            throw new EjarException(__('أدخل مساحة الوحدة :unit قبل تسجيلها.', ['unit' => $unit->number ?: $unit->title]));
        }
        $unitId = $this->api->createUnit($propertyId, self::unitPayload($details));
        $unit->forceFill(['ejar_property_id' => $propertyId, 'ejar_unit_id' => $unitId, 'ejar_profile' => $details])->save();
    }

    /** The owner as an Ejar entity (looked up once, then kept on the owner). */
    public function ownerEntity(Owner $owner): array
    {
        $organization = $owner->id_type === IdType::CommercialRegister;
        if (blank($owner->ejar_entity_id)) {
            $entity = $organization
                ? $this->api->organization((string) $owner->id_number, (string) $owner->birth_date?->format('Y-m-d'))
                : $this->api->individual((string) $owner->id_number, $owner->id_type?->value ?? 'national_id');
            $owner->forceFill(['ejar_entity_id' => $entity['id'] ?? null, 'ejar_verification' => $entity['verification_status'] ?? 'found', 'ejar_verified_at' => now()])->save();
        }
        if (blank($owner->ejar_entity_id)) {
            throw new EjarException(__('لم تجد منصة إيجار المالك :name برقم هويته.', ['name' => $owner->full_name]));
        }

        return ['type' => $organization ? 'organization_entities' : 'individual_entities', 'id' => (string) $owner->ejar_entity_id];
    }

    public function propertyPayload(Unit $unit, array $p, array $scans = []): array
    {
        $owners = $unit->owners()->orderByPivot('share_percent', 'desc')->get()
            ->map(fn (Owner $owner) => ['type' => 'property_owners', 'attributes' => ['role' => 'property_owner'], 'relationships' => ['entity' => ['data' => $this->ownerEntity($owner)]]])
            ->values()->all();
        $electronic = in_array($p['ownership_document_type'] ?? null, config('ejar.electronic_documents'), true);
        $holder = self::holder($unit);

        return [
            'ownership_document' => ['type' => 'ownership_documents', 'attributes' => array_filter([
                'document_number' => (string) $p['document_number'],
                'issue_place' => $p['issue_place'] ?? null,
                'issued_by' => $p['issued_by'] ?? null,
                'issued_date' => self::date($p['issued_date'] ?? null),
                'legal_document_type_name' => $p['legal_document_type_name'] ?? null,
                'ownership_document_type' => $p['ownership_document_type'] ?? 'electronic_title_deed',
                'scanned_documents' => $electronic ? null : array_map(fn ($f) => 'data:'.$f['mime'].';base64,'.base64_encode($f['content']), $scans),
            ], fn ($v) => $v !== null && $v !== [])],
            // the specification shows one owner as an object; several owners go as a list
            'owners' => count($owners) === 1 ? $owners[0] : $owners,
            'property' => ['type' => 'portfolio_properties', 'attributes' => [
                'root_uuid' => null,
                'address' => ['attributes' => [
                    'region' => $p['region'] ?? null,
                    'district' => $p['district'] ?? null,
                    'city' => $p['city'] ?? null,
                    'building_number' => (string) ($p['building_number'] ?? ''),
                    'street_name' => $p['street_name'] ?? null,
                    'postal_code' => (string) ($p['postal_code'] ?? ''),
                    'additional_number' => (string) ($p['additional_number'] ?? ''),
                    'latitude' => $holder->latitude !== null ? (float) $holder->latitude : null,
                    'longitude' => $holder->longitude !== null ? (float) $holder->longitude : null,
                ]],
                'contract_type' => 'residential',
                'property_name' => $p['property_name'] ?? null,
                'property_number' => (string) ($p['property_number'] ?? ''),
                'total_floors' => (int) ($p['total_floors'] ?? 1),
                'property_usage' => $p['property_usage'] ?? 'residential_families',
                'property_type' => $p['property_type'] ?? 'building',
                'established_date' => self::date($p['established_date'] ?? null),
                'units_per_floor' => (string) ((int) ($p['units_per_floor'] ?? 1)),
                'associated_facilities' => array_map('intval', array_replace(array_fill_keys(array_keys(config('ejar.facilities')), 0), (array) ($p['facilities'] ?? []))),
                'compound_name' => null,
            ]],
        ];
    }

    public static function unitPayload(array $u): array
    {
        $furnished = (bool) ($u['is_furnished'] ?? false);

        return [
            'root_uuid' => null,
            'availability' => null,
            'agreement_status' => null,
            'amenities' => array_map('intval', array_replace(array_fill_keys(array_keys(config('ejar.amenities')), 0), (array) ($u['amenities'] ?? [])))
                + array_map('boolval', array_replace(array_fill_keys(array_keys(config('ejar.fixtures')), false), (array) ($u['fixtures'] ?? []))),
            'utilities' => array_map(fn ($v) => filled($v) ? (string) $v : null, [
                'gas_meter' => $u['gas_meter'] ?? null, 'electricity_meter' => $u['electricity_meter'] ?? null, 'water_meter' => $u['water_meter'] ?? null,
            ]),
            'unit_number' => (string) ($u['unit_number'] ?? ''),
            'floor_number' => (string) ($u['floor_number'] ?? 'G'),
            'is_furnished' => $furnished,
            'furnish_type' => $furnished ? ($u['furnish_type'] ?? 'furnish_used') : null,
            'unit_type' => $u['unit_type'] ?? 'apartment',
            'unit_usage' => $u['unit_usage'] ?? 'residential_families',
            'area' => (float) ($u['area'] ?? 0),
            'established_date' => self::date($u['established_date'] ?? null),
            'unit_direction' => $u['unit_direction'] ?? null,
            'unit_finishing' => $u['unit_finishing'] ?? null,
            'unit_dimensions' => array_map(fn ($v) => filled($v) ? (string) $v : null, array_replace(['length' => null, 'width' => null, 'height' => null], (array) ($u['dimensions'] ?? []))),
            'include_mezzanine' => (bool) ($u['include_mezzanine'] ?? false),
            'rooms' => (int) ($u['rooms'] ?? 0),
        ];
    }

    /** The new Ejar property belongs to the building (and every unit in it not linked yet), or to the lone unit. */
    private function linkProperty(Unit $unit, string $propertyId): void
    {
        DB::transaction(function () use ($unit, $propertyId) {
            $holder = self::holder($unit);
            if ($holder instanceof Building) {
                $holder->forceFill(['ejar_property_id' => $propertyId])->save();
                $holder->units()->whereNull('ejar_property_id')->update(['ejar_property_id' => $propertyId]);
            }
            $unit->forceFill(['ejar_property_id' => $propertyId])->save();
        });
    }

    // ------------------------------------------------------------------ helpers

    /** @return array{electricity: ?string, water: ?string, gas: ?string} */
    private static function meters(Unit $unit): array
    {
        $find = fn (array $words) => $unit->meters->first(fn (UnitMeter $m) => collect($words)->contains(fn ($w) => mb_stripos((string) $m->meterType?->title, $w) !== false))?->number;

        return ['electricity' => $find(['كهرباء', 'electric']), 'water' => $find(['مياه', 'ماء', 'water']), 'gas' => $find(['غاز', 'gas'])];
    }

    private static function unitType(Unit $unit): string
    {
        $title = (string) ($unit->category?->title ?? '').' '.$unit->title;
        foreach (['فيلا' => 'villa', 'دوبلكس' => 'duplex', 'دور' => 'floor', 'استوديو' => 'studio', 'غرفة' => 'room', 'ملحق' => 'annex', 'تاون' => 'townhouse'] as $word => $type) {
            if (mb_stripos($title, $word) !== false) {
                return $type;
            }
        }

        return 'apartment';
    }

    public static function region(?string $city): ?string
    {
        foreach (config('ejar.city_regions') as $name => $region) {
            if ($city !== null && mb_stripos($city, $name) !== false) {
                return $region;
            }
        }

        return null;
    }

    private static function digits(?string $value, int $max): ?string
    {
        $digits = preg_replace('/\D/', '', strtr((string) $value, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));

        return $digits === '' ? null : substr($digits, -$max);
    }

    private static function date(mixed $value): ?string
    {
        return blank($value) ? null : substr((string) ($value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value), 0, 10);
    }

    /** Deed dates are often kept in Hijri ("1440/03/12"); Ejar wants the Gregorian date. */
    public static function gregorian(?string $date): ?string
    {
        if (blank($date) || ! preg_match('/^(\d{4})[\/-](\d{1,2})[\/-](\d{1,2})/', trim($date), $m)) {
            return null;
        }
        try {
            return (int) $m[1] < 1700 ? Hijri::parse("{$m[1]}/{$m[2]}/{$m[3]}")->format('Y-m-d') : sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        } catch (\Throwable) {
            return null;
        }
    }
}
