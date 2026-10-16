<?php

namespace App\Services\Payouts;

use App\Enums\LedgerDirection;
use App\Enums\OwnerPayoutStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\BankAccount;
use App\Models\Owner;
use App\Models\OwnerPayout;
use App\Models\OwnerPayoutItem;
use App\Models\OwnerTransaction;
use App\Services\DocumentNumberGenerator;
use App\Services\Finance\VoucherService;
use App\Services\Import\Spreadsheet;
use App\Services\Messaging\DomainNotifications;
use App\Support\Iban;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Paying owners their dues in one run instead of one voucher at a time:
 *   1. prepare — every chosen owner's open ledger entries up to a date are netted (rent shares minus
 *      commission, expenses and taxes) into one payment voucher, and the run gets a bulk-transfer file;
 *   2. the office uploads the file to its bank, then marks the run transferred — only then are the owners told;
 *   3. a run not yet transferred can be cancelled, which deletes its vouchers and reopens the entries.
 */
class OwnerPayouts
{
    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly DocumentNumberGenerator $numbers,
        private readonly DomainNotifications $notifications,
    ) {}

    /**
     * Owners with a positive net balance up to the date, largest first.
     *
     * @return Collection<int, array{owner: Owner, dues: float, deductions: float, net: float, entries: int, iban: ?string, bank: ?string, ready: bool}>
     */
    public function candidates(mixed $until, float $minimum = 0.0): Collection
    {
        $totals = $this->openEntries($until)
            ->selectRaw('owner_id, direction, SUM(amount) as total, COUNT(*) as entries')
            ->groupBy('owner_id', 'direction')->get()->groupBy('owner_id');
        $owners = Owner::query()->with('bank')->whereKey($totals->keys())->get()->keyBy('id');

        return $totals->map(function (Collection $rows, $ownerId) use ($owners) {
            $owner = $owners->get($ownerId);
            if ($owner === null) {
                return null;
            }
            $sum = fn (LedgerDirection $d) => round((float) $rows->firstWhere('direction', $d->value)?->total, 2);
            $dues = $sum(LedgerDirection::Debit);
            $deductions = $sum(LedgerDirection::Credit);

            return [
                'owner' => $owner,
                'dues' => $dues,
                'deductions' => $deductions,
                'net' => round($dues - $deductions, 2),
                'entries' => (int) $rows->sum('entries'),
                'iban' => $owner->iban,
                'bank' => $owner->bank?->title ?? Iban::bank($owner->iban)[0] ?? null,
                'ready' => Iban::valid($owner->iban),
            ];
        })->filter(fn ($row) => $row !== null && $row['net'] > 0 && $row['net'] >= $minimum)
            ->sortByDesc('net')->values();
    }

    /** @param list<int> $ownerIds */
    public function prepare(array $ownerIds, mixed $until, ?BankAccount $from = null, mixed $issuedAt = null, ?string $notes = null): OwnerPayout
    {
        $until = CarbonImmutable::parse($until)->startOfDay();
        $rows = $this->candidates($until)->filter(fn ($row) => in_array($row['owner']->id, array_map('intval', $ownerIds), true));
        if ($rows->isEmpty()) {
            throw new BusinessRuleException(__('لا توجد مستحقات صافية للملاك المحددين حتى هذا التاريخ'));
        }
        if (($missing = $rows->reject(fn ($row) => $row['ready']))->isNotEmpty()) {
            throw new BusinessRuleException(__('أكمل الآيبان للملاك: :names', ['names' => $missing->map(fn ($row) => $row['owner']->full_name)->join('، ')]));
        }
        $issuedAt = CarbonImmutable::parse($issuedAt ?? today());

        return DB::transaction(function () use ($rows, $until, $from, $issuedAt, $notes) {
            $payout = OwnerPayout::create([
                'number' => $this->numbers->next('owner_payout'),
                'issued_at' => $issuedAt,
                'dues_until' => $until,
                'bank_account_id' => $from?->id,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            foreach ($rows as $row) {
                /** @var Owner $owner */
                $owner = $row['owner'];
                $entries = $this->openEntries($until)->where('owner_id', $owner->id)->get(['id', 'payment_voucher_id']);
                $entryIds = $entries->modelKeys();
                $voucher = $this->vouchers->createPayment([
                    'issued_at' => $issuedAt,
                    'party_type' => $owner->getMorphClass(),
                    'party_id' => $owner->id,
                    'amount' => $row['net'],
                    'payment_method' => 'transfer',
                    'bank_account_id' => $from?->id,
                    'bank_name' => $row['bank'],
                    'reason' => __('توزيع مستحقات المالك حتى :date — دفعة :number', ['date' => $until->format('Y-m-d'), 'number' => $payout->number]),
                ], null, $entries->whereNull('payment_voucher_id')->modelKeys(), notify: false);
                // expense charges stay linked to the expense voucher that created them; they are only marked settled
                OwnerTransaction::query()->whereKey($entries->whereNotNull('payment_voucher_id')->modelKeys())
                    ->update(['is_settled' => true, 'settled_at' => now()]);

                $payout->items()->create([
                    'owner_id' => $owner->id,
                    'payment_voucher_id' => $voucher->id,
                    'dues' => $row['dues'],
                    'deductions' => $row['deductions'],
                    'amount' => $row['net'],
                    'entries_count' => count($entryIds),
                    'entry_ids' => $entryIds,
                    'beneficiary' => $owner->bank_beneficiary ?: $owner->full_name,
                    'iban' => Iban::normalize($owner->iban),
                    'bank_name' => $row['bank'],
                ]);
            }

            $payout->forceFill(['owners_count' => $rows->count(), 'total' => round($rows->sum('net'), 2)])->save();

            return $payout;
        });
    }

    /** The bank executed the transfers: close the run and tell each owner. */
    public function markTransferred(OwnerPayout $payout): void
    {
        $this->ensurePrepared($payout);
        $payout->forceFill(['status' => OwnerPayoutStatus::Transferred, 'transferred_at' => now()])->save();
        foreach ($payout->items()->with('paymentVoucher.party')->get() as $item) {
            if ($item->paymentVoucher !== null) {
                $this->notifications->paymentIssued($item->paymentVoucher);
            }
        }
    }

    /** Undo a run that was not sent: its vouchers are deleted and the owners' entries are open again. */
    public function cancel(OwnerPayout $payout): void
    {
        $this->ensurePrepared($payout);
        DB::transaction(function () use ($payout) {
            foreach ($payout->items()->with('paymentVoucher')->get() as $item) {
                if ($item->paymentVoucher !== null) {
                    $this->vouchers->delete($item->paymentVoucher);
                }
                OwnerTransaction::query()->whereKey($item->entry_ids ?? [])->update(['is_settled' => false, 'settled_at' => null]);
            }
            $payout->forceFill(['status' => OwnerPayoutStatus::Cancelled, 'cancelled_at' => now()])->save();
        });
    }

    /** The bulk-transfer sheet to upload to the bank (Excel, or CSV for banks that want it). Returns the file path. */
    public function bankFile(OwnerPayout $payout, string $format = 'xlsx'): string
    {
        $headers = [__('م'), __('اسم المستفيد'), __('رقم الآيبان'), __('البنك'), __('رمز السويفت'), __('المبلغ'), __('المرجع'), __('الغرض')];
        $rows = $payout->items()->with('paymentVoucher')->orderBy('id')->get()->values()->map(fn (OwnerPayoutItem $item, int $i) => [
            (string) ($i + 1),
            $item->beneficiary,
            $item->iban,
            (string) $item->bank_name,
            (string) (Iban::bank($item->iban)[1] ?? ''),
            number_format((float) $item->amount, 2, '.', ''),
            $payout->number.' / '.($item->paymentVoucher?->number ?? $item->id),
            __('توزيع إيجارات'),
        ])->all();

        $path = tempnam(sys_get_temp_dir(), 'payout').'.'.$format;
        if ($format === 'csv') {
            $out = fopen($path, 'w');
            fwrite($out, "\u{FEFF}"); // Excel opens UTF-8 Arabic correctly with a BOM
            foreach ([$headers, ...$rows] as $row) {
                fputcsv($out, $row, escape: '');
            }
            fclose($out);
        } else {
            Spreadsheet::template($path, $headers, $rows);
        }

        return $path;
    }

    /**
     * Open entries owed to or by owners, due on or before the date. Expense charges are open while linked to the
     * expense voucher that created them, so "open" means not settled, whatever voucher they point to.
     */
    private function openEntries(mixed $until): Builder
    {
        return OwnerTransaction::query()->whereNotNull('owner_id')->where('is_settled', false)
            ->whereDate('due_date', '<=', CarbonImmutable::parse($until)->toDateString());
    }

    private function ensurePrepared(OwnerPayout $payout): void
    {
        if ($payout->status !== OwnerPayoutStatus::Prepared) {
            throw new BusinessRuleException(__('هذه الدفعة :status', ['status' => $payout->status->getLabel()]));
        }
    }
}
