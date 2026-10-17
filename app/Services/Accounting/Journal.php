<?php

namespace App\Services\Accounting;

use App\Exceptions\BusinessRuleException;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\EmployeeAdvance;
use App\Models\EndOfServiceSettlement;
use App\Models\Installment;
use App\Models\InstallmentPayment;
use App\Models\JournalEntry;
use App\Models\OwnerTransaction;
use App\Models\PaymentVoucher;
use App\Models\PayrollItem;
use App\Models\ReceiptVoucher;
use App\Models\Tenant;
use App\Services\DocumentNumberGenerator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The general journal. Documents re-post their entry whenever they (or what they are linked to) change,
 * once the surrounding transaction commits; manual entries go through post().
 */
class Journal
{
    /** Models whose changes post entries of their own. */
    public const SOURCES = [ReceiptVoucher::class, PaymentVoucher::class, OwnerTransaction::class, BankAccount::class];

    /** @var array<string, Model> */
    private static array $pending = [];

    public function __construct(private readonly DocumentNumberGenerator $numbers) {}

    /** Re-post this document after the current transaction commits (once, however often it is touched). */
    public static function touch(?Model $source): void
    {
        if ($source === null || ! tenancy()->initialized) {
            return;
        }
        $key = tenant()->getTenantKey().':'.$source::class.':'.$source->getKey();
        if (isset(self::$pending[$key])) {
            return;
        }
        self::$pending[$key] = $source;
        $office = tenant()->getTenantKey();
        DB::afterCommit(function () use ($key, $office) {
            $source = self::$pending[$key] ?? null;
            unset(self::$pending[$key]);
            if ($source === null) {
                return;
            }
            // a callback held by an outer (central) transaction may run after the office's context ended
            tenant()?->getTenantKey() === $office
                ? app(self::class)->sync($source)
                : Tenant::find($office)?->run(fn () => app(self::class)->sync($source));
        });
    }

    /** Keep the journal in step with the documents and with the links that change what they post. */
    public static function observe(): void
    {
        foreach (self::SOURCES as $class) {
            $class::saved(fn (Model $m) => self::touch($m));
            $class::deleted(fn (Model $m) => self::touch($m));
            if (method_exists($class, 'restored')) {
                $class::restored(fn (Model $m) => self::touch($m));
            }
        }
        // links made after the voucher was saved (installments collected by it, advances / salaries paid by it…)
        $linked = function (Model $m) {
            foreach (['receipt_voucher_id' => ReceiptVoucher::class, 'payment_voucher_id' => PaymentVoucher::class] as $column => $class) {
                if (! array_key_exists($column, $m->getAttributes())) {
                    continue;
                }
                foreach (array_unique(array_filter([$m->getAttribute($column), $m->getOriginal($column)])) as $id) {
                    self::touch((new $class)->forceFill(['id' => $id]));
                }
            }
        };
        foreach ([Installment::class, InstallmentPayment::class, OwnerTransaction::class,
            EmployeeAdvance::class, PayrollItem::class, EndOfServiceSettlement::class] as $class) {
            $class::saved(fn (Model $m) => ($m->wasRecentlyCreated || $m->wasChanged(['receipt_voucher_id', 'payment_voucher_id', 'amount', 'vat_amount', 'buyer_commission'])) && $linked($m));
            $class::deleted($linked);
        }
    }

    /** Bring the document's entry in line with the document: create, replace or remove it. */
    public function sync(Model $source): ?JournalEntry
    {
        $fresh = $source::query()->withoutGlobalScope('branch')->find($source->getKey()); // a deleted document has no entry
        $existing = JournalEntry::query()->withoutGlobalScopes()->where('source_type', $source->getMorphClass())->where('source_id', $source->getKey())->first();
        $posting = $fresh === null ? null : Postings::for($fresh);

        if ($posting === null) {
            $existing?->delete();

            return null;
        }
        [$date, $description, $lines] = $posting;

        return $this->write($existing ?? new JournalEntry(['source_type' => $source->getMorphClass(), 'source_id' => $source->getKey()]),
            ['entry_date' => Carbon::parse($date)->toDateString(), 'description' => $description, 'branch_id' => $fresh->branch_id ?? null], $lines);
    }

    /**
     * A manual entry (opening balances, accruals, corrections).
     *
     * @param  list<array{account_id: int, debit?: float|string|null, credit?: float|string|null, memo?: ?string}>  $lines
     */
    public function post(array $attributes, array $lines, ?JournalEntry $entry = null): JournalEntry
    {
        $rows = array_map(fn (array $l) => [(int) $l['account_id'], (float) ($l['debit'] ?? 0), (float) ($l['credit'] ?? 0), $l['memo'] ?? null], $lines);

        return $this->write($entry ?? new JournalEntry(['created_by' => auth()->id()]), $attributes, $rows, manual: true);
    }

    /** Re-post every document (after an upgrade, an import or a rule change). @return int entries written */
    public function rebuild(): int
    {
        Accounts::flush();
        $count = 0;
        DB::transaction(function () use (&$count) {
            JournalEntry::query()->withoutGlobalScopes()->whereNotNull('source_type')->delete();
            foreach (self::SOURCES as $class) {
                $class::query()->withoutGlobalScope('branch')->lazyById(200)->each(function (Model $source) use (&$count) {
                    $count += $this->sync($source) !== null ? 1 : 0;
                });
            }
        });

        return $count;
    }

    /** @param  list<array>  $lines  [system key | account id, debit, credit, memo?, owner id?] */
    private function write(JournalEntry $entry, array $attributes, array $lines, bool $manual = false): JournalEntry
    {
        $rows = [];
        foreach ($lines as $line) {
            [$account, $debit, $credit] = $line;
            $debit = round((float) $debit, 2);
            $credit = round((float) $credit, 2);
            if ($debit < 0 || $credit < 0) {
                [$debit, $credit] = [max($debit, 0) + max(-$credit, 0), max($credit, 0) + max(-$debit, 0)];
            }
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }
            $rows[] = [
                'account_id' => is_int($account) ? $account : Accounts::id($account),
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line[3] ?? null,
                'owner_id' => $line[4] ?? null,
            ];
        }

        $debits = round(array_sum(array_column($rows, 'debit')), 2);
        $credits = round(array_sum(array_column($rows, 'credit')), 2);
        if (count($rows) < 2 || abs($debits - $credits) > 0.004) {
            throw new BusinessRuleException(__('القيد غير متوازن: المدين :debit والدائن :credit', ['debit' => number_format($debits, 2), 'credit' => number_format($credits, 2)]));
        }
        if ($manual) {
            $closed = Account::query()->whereKey(array_column($rows, 'account_id'))->where(fn ($q) => $q->where('is_group', true)->orWhere('is_active', false))->value('name');
            if ($closed !== null) {
                throw new BusinessRuleException(__('الحساب :name رئيسي أو موقوف ولا يقبل قيودًا', ['name' => $closed]));
            }
        }

        return DB::transaction(function () use ($entry, $attributes, $rows, $debits) {
            $entry->fill($attributes + ['total' => $debits]);
            $entry->number ??= $this->numbers->next('journal_entry', (int) Carbon::parse($entry->entry_date)->format('Y'));
            $entry->save();
            $entry->lines()->delete();
            $entry->lines()->createMany($rows);

            return $entry;
        });
    }
}
