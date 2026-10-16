<?php

namespace App\Services\Import\Importers;

use App\Models\Lookups\Bank;
use App\Models\Owner;
use App\Support\Iban;

/** Owners, with the IBAN their dues are transferred to (the bank is recognised from it). */
class OwnersImporter extends PeopleImporter
{
    public static function key(): string
    {
        return 'owners';
    }

    public static function title(): string
    {
        return __('الملاك');
    }

    protected static function model(): string
    {
        return Owner::class;
    }

    public static function columns(): array
    {
        return parent::columns() + [
            'iban' => ['الآيبان', false, 'SA0380000000608010167519'],
        ];
    }

    public function problems(array $row): array
    {
        $f = static::fields($row);
        $problems = parent::problems($row);
        if (filled($f['iban']) && ! Iban::valid($f['iban'])) {
            $problems[] = __('رقم الآيبان غير صحيح؛ تأكد من الأرقام (24 خانة تبدأ بـ SA)');
        }

        return $problems;
    }

    protected function extra(array $f): array
    {
        $iban = Iban::normalize($f['iban']);
        $bank = Iban::bank($iban)[0] ?? null;

        return [
            'iban' => $iban,
            'bank_id' => $bank !== null ? Bank::query()->firstOrCreate(['title' => $bank])->id : null,
        ];
    }
}
