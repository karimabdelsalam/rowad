<?php

namespace App\Services\Import\Importers;

use App\Enums\ClientKind;
use App\Models\Client;

/** Tenants and buyers; a company is marked by its type or by a company name. */
class ClientsImporter extends PeopleImporter
{
    public static function key(): string
    {
        return 'clients';
    }

    public static function title(): string
    {
        return __('العملاء (مستأجرون ومشترون)');
    }

    protected static function model(): string
    {
        return Client::class;
    }

    public static function columns(): array
    {
        return parent::columns() + [
            'kind' => ['نوع العميل', false, 'فرد'],
            'company_name' => ['اسم المنشأة', false, ''],
            'company_registration_number' => ['السجل التجاري', false, ''],
            'vat_number' => ['الرقم الضريبي', false, ''],
        ];
    }

    public function problems(array $row): array
    {
        $f = static::fields($row);
        $problems = parent::problems($row);
        if (filled($f['kind']) && static::kind($f) === null) {
            $problems[] = __('نوع العميل «:value» غير معروف (فرد / منشأة)', ['value' => $f['kind']]);
        }

        return $problems;
    }

    protected function extra(array $f): array
    {
        return [
            'kind' => (static::kind($f) ?? (filled($f['company_name']) ? ClientKind::Company : ClientKind::Individual))->value,
            'company_name' => $f['company_name'],
            'company_registration_number' => static::digits($f['company_registration_number']),
            'vat_number' => static::digits($f['vat_number']),
        ];
    }

    private static function kind(array $f): ?ClientKind
    {
        $value = trim((string) $f['kind']);
        if (in_array($value, ['منشأة', 'شركة', 'مؤسسة', 'company'], true)) {
            return ClientKind::Company;
        }

        return static::enum(ClientKind::class, $value);
    }
}
