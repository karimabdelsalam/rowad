<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DocumentNumberGenerator
{
    public const PREFIXES = [
        'rent_contract' => 'RC',
        'sale_contract' => 'SC',
        'receipt_voucher' => 'RV',
        'payment_voucher' => 'PV',
        'invoice' => 'INV',
    ];

    /** Next number like "RV-2026-00042"; atomic under concurrent requests. */
    public function next(string $key, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        $number = DB::transaction(function () use ($key, $year) {
            $table = DB::table('document_sequences');
            $row = $table->where(['key' => $key, 'year' => $year])->lockForUpdate()->first();

            if ($row === null) {
                $table->insert(['key' => $key, 'year' => $year, 'last_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

                return 1;
            }

            $table->where('id', $row->id)->update(['last_number' => $row->last_number + 1, 'updated_at' => now()]);

            return $row->last_number + 1;
        });

        return sprintf('%s-%d-%05d', self::PREFIXES[$key] ?? strtoupper($key), $year, $number);
    }
}
