<?php

namespace App\Services\Accounting;

use App\Enums\AccountType;
use App\Models\Account;
use App\Models\BankAccount;
use Illuminate\Support\Facades\DB;

/** The accounts the automatic postings use, found by their system key; one sub-account per bank account. */
final class Accounts
{
    /** @var array<string, int> "tenant|key" => account id */
    private static array $ids = [];

    public static function id(string $key): int
    {
        return self::$ids[self::scope().$key] ??= (int) Account::query()->where('system_key', $key)->valueOrFail('id');
    }

    /** The cash box, or the ledger account of the bank account the money went through. */
    public static function money(?int $bankAccountId): int
    {
        if ($bankAccountId === null) {
            return self::id('cash');
        }

        return self::$ids[self::scope().'bank:'.$bankAccountId] ??= self::forBank(BankAccount::withTrashed()->findOrFail($bankAccountId))->id;
    }

    public static function forBank(BankAccount $bank): Account
    {
        return Account::query()->where('bank_account_id', $bank->id)->first() ?? DB::transaction(function () use ($bank) {
            $parent = Account::query()->where('system_key', 'cash_and_banks')->firstOrFail();
            $last = Account::query()->where('parent_id', $parent->id)->pluck('code')->map(fn ($c) => (int) $c)->max() ?? (int) ($parent->code.'01');
            $name = trim(($bank->holder_name ?: __('حساب بنكي')).($bank->account_number ? ' — '.$bank->account_number : ''));

            return Account::create([
                'code' => (string) ($last + 1),
                'name' => $name,
                'name_en' => $name,
                'type' => AccountType::Asset,
                'parent_id' => $parent->id,
                'bank_account_id' => $bank->id,
            ]);
        });
    }

    public static function flush(): void
    {
        self::$ids = [];
    }

    private static function scope(): string
    {
        return (tenant()?->getTenantKey() ?? 'central').'|';
    }
}
