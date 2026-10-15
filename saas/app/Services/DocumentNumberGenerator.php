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

        return sprintf('%s-%d-%05d', self::PREFIXES[$key] ?? strtoupper($key), $year, $this->sequence($key, $year));
    }

    /** The number the next document of this key will get (legacy home/serial shows the AUTO_INCREMENT). */
    public function upcoming(string $key, ?int $year = null): int
    {
        $year ??= (int) now()->format('Y');

        return (int) DB::table('document_sequences')->where(['key' => $key, 'year' => $year])->value('last_number') + 1;
    }

    /** Start the next document at $number; never backwards, so numbers are not reused (legacy rule). */
    public function startAt(string $key, int $number, ?int $year = null): void
    {
        $year ??= (int) now()->format('Y');
        if ($number < $this->upcoming($key, $year)) {
            throw new \App\Exceptions\BusinessRuleException(__('لا يمكن تعيين احد اعدادات الترقيم برقم اقل من الحالي'));
        }

        DB::table('document_sequences')->updateOrInsert(['key' => $key, 'year' => $year], ['last_number' => $number - 1, 'updated_at' => now()]);
    }

    /** Raw next sequence value for a key and year. */
    public function sequence(string $key, ?int $year = null): int
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($key, $year) {
            $table = DB::table('document_sequences');
            $row = $table->where(['key' => $key, 'year' => $year])->lockForUpdate()->first();

            if ($row === null) {
                $table->insert(['key' => $key, 'year' => $year, 'last_number' => 1, 'created_at' => now(), 'updated_at' => now()]);

                return 1;
            }

            $table->where('id', $row->id)->update(['last_number' => $row->last_number + 1, 'updated_at' => now()]);

            return $row->last_number + 1;
        });
    }
}
