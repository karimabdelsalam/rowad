<?php

namespace App\Services\Import\Importers;

use App\Enums\IdType;
use App\Services\Import\Importer;
use Illuminate\Database\Eloquent\Model;

/** Owners and clients: the four-part name, identity and contact; matched by ID number, then mobile. */
abstract class PeopleImporter extends Importer
{
    /** @return class-string<Model> */
    abstract protected static function model(): string;

    public static function columns(): array
    {
        return [
            'first_name' => ['الاسم الأول', true, 'سعد'],
            'father_name' => ['اسم الأب', false, 'محمد'],
            'grandfather_name' => ['اسم الجد', false, 'عبدالله'],
            'family_name' => ['اسم العائلة', false, 'العتيبي'],
            'id_type' => ['نوع الهوية', false, 'هوية وطنية'],
            'id_number' => ['رقم الهوية', false, '1012345678'],
            'nationality' => ['الجنسية', false, 'سعودي'],
            'birth_date' => ['تاريخ الميلاد', false, '1980-05-14'],
            'mobile' => ['الجوال', false, '0501234567'],
            'email' => ['البريد الإلكتروني', false, ''],
            'address' => ['العنوان', false, 'الرياض - حي العليا'],
            'notes' => ['ملاحظات', false, ''],
        ];
    }

    public function problems(array $row): array
    {
        $f = static::fields($row);
        $problems = static::missing($f);
        if (filled($f['id_type']) && static::enum(IdType::class, $f['id_type']) === null) {
            $problems[] = __('نوع الهوية «:value» غير معروف', ['value' => $f['id_type']]);
        }
        if (filled($f['birth_date']) && static::date($f['birth_date']) === null) {
            $problems[] = __('تاريخ الميلاد غير صحيح');
        }
        if (filled($f['email']) && ! filter_var($f['email'], FILTER_VALIDATE_EMAIL)) {
            $problems[] = __('البريد الإلكتروني غير صحيح');
        }

        return $problems;
    }

    public function import(array $row, array $options): string
    {
        $f = static::fields($row);
        $values = array_filter([
            'first_name' => $f['first_name'],
            'father_name' => $f['father_name'],
            'grandfather_name' => $f['grandfather_name'],
            'family_name' => $f['family_name'],
            'id_type' => static::enum(IdType::class, $f['id_type'])?->value,
            'id_number' => static::digits($f['id_number']) ?: null,
            'nationality' => $f['nationality'],
            'birth_date' => static::date($f['birth_date'])?->toDateString(),
            'mobile' => static::mobile($f['mobile']),
            'email' => $f['email'],
            'address' => $f['address'],
            'notes' => $f['notes'],
        ] + $this->extra($f), fn ($v) => filled($v));

        $existing = static::find($values['id_number'] ?? null, $values['mobile'] ?? null);
        if ($existing !== null) {
            if (! ($options['update_existing'] ?? false)) {
                return 'skipped';
            }
            $existing->fill($values)->save();

            return 'updated';
        }
        $model = static::model();
        $model::create($values);

        return 'created';
    }

    /** Matched by ID number first (unique per person), then by mobile. */
    public static function find(?string $idNumber, ?string $mobile): ?Model
    {
        $model = static::model();

        return (filled($idNumber) ? $model::query()->where('id_number', $idNumber)->first() : null)
            ?? (filled($mobile) ? $model::query()->where('mobile', $mobile)->first() : null);
    }

    /** Extra columns of a subclass, already mapped to model attributes. */
    protected function extra(array $fields): array
    {
        return [];
    }
}
