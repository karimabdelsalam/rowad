<?php

namespace App\Services\Units;

use App\Exceptions\BusinessRuleException;
use App\Models\CustomField;
use App\Models\Unit;

/** Owners' shares and custom field values of a unit (legacy building.owner JSON + custom_fields_data). */
class UnitOwnership
{
    /** @param list<array{owner_id:int|string, share_percent:float|string, expenses_percent:float|string}> $rows */
    public function syncOwners(Unit $unit, array $rows): void
    {
        $rows = array_values(array_filter($rows, fn ($r) => filled($r['owner_id'] ?? null)));
        if ($rows === []) {
            throw new BusinessRuleException(__('نسيت اختيار ملاك العقار لا يمكن المتابعة'));
        }
        if (abs(array_sum(array_map(fn ($r) => (float) $r['share_percent'], $rows)) - 100) > 0.01) {
            throw new BusinessRuleException(__('مجموع نسب الملكية يجب أن يساوي 100%'));
        }
        if (array_sum(array_map(fn ($r) => (float) ($r['expenses_percent'] ?? 0), $rows)) > 100.01) {
            throw new BusinessRuleException(__('مجموع نسب تحمل المصروفات لا يمكن أن يتجاوز 100%'));
        }

        $unit->owners()->sync(collect($rows)->mapWithKeys(fn ($r) => [(int) $r['owner_id'] => [
            'share_percent' => (float) $r['share_percent'],
            'expenses_percent' => (float) ($r['expenses_percent'] ?? 0),
        ]])->all());
    }

    /** @return list<array{owner_id:int, share_percent:float, expenses_percent:float}> */
    public function ownersForForm(Unit $unit): array
    {
        return $unit->owners()->get()->map(fn ($o) => [
            'owner_id' => $o->id,
            'share_percent' => (float) $o->pivot->share_percent,
            'expenses_percent' => (float) $o->pivot->expenses_percent,
        ])->all();
    }

    /** @param array<int|string, mixed> $values custom_field_id => value */
    public function syncCustomFields(Unit $unit, array $values): void
    {
        $fields = CustomField::whereIn('id', array_keys($values))->get()->keyBy('id');
        foreach ($values as $fieldId => $value) {
            $field = $fields->get((int) $fieldId);
            if ($field === null) {
                continue;
            }
            if ($field->is_required && blank($value)) {
                throw new BusinessRuleException(__('الحقل :field إلزامي', ['field' => $field->title]));
            }
            $unit->customFieldValues()->updateOrCreate(['custom_field_id' => $field->id], ['value' => is_bool($value) ? (int) $value : $value]);
        }
    }

    /** @return array<int, mixed> */
    public function customFieldsForForm(Unit $unit): array
    {
        return $unit->customFieldValues()->pluck('value', 'custom_field_id')->all();
    }
}
