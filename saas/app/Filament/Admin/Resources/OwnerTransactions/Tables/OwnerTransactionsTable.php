<?php

namespace App\Filament\Admin\Resources\OwnerTransactions\Tables;

use App\Enums\LedgerDirection;
use App\Filament\Admin\Resources\PaymentVouchers\PaymentVoucherResource;
use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Models\OwnerTransaction;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Legacy transactions module: the owners' ledger (debit = owed to the owner, credit = deducted).
 * pending/processed lists, finish (settle) / cancel (return), and vouchers from selected entries (statmin/statmout).
 * Entries are never deleted — they are produced by collections, expenses and fees.
 */
class OwnerTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['owner', 'unit', 'type', 'receiptVoucher', 'paymentVoucher']))
            ->defaultSort('due_date', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('owner.full_name')->label(__('المالك'))->placeholder(__('المؤسسة'))->searchable(),
                TextColumn::make('unit.title')->label(__('الوحدة'))->searchable(),
                TextColumn::make('type.title')->label(__('النوع')),
                TextColumn::make('direction')->badge(),
                TextColumn::make('amount')->numeric(2)->sortable(),
                TextColumn::make('vat_amount')->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('due_date')->date('Y-m-d')->sortable(),
                IconColumn::make('is_settled')->boolean(),
                TextColumn::make('settled_at')->date('Y-m-d')->toggleable(),
                TextColumn::make('receiptVoucher.number')->label(__('سند القبض'))->toggleable(),
                TextColumn::make('paymentVoucher.number')->label(__('سند الصرف'))->toggleable(),
                TextColumn::make('installment_id')->label(__('القسط'))->prefix('#')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_settled')->label(__('الحالة'))
                    ->placeholder(__('الكل'))->trueLabel(__('مُرحّلة'))->falseLabel(__('معلّقة')),
                SelectFilter::make('owner_id')->label(__('المالك'))->relationship('owner', 'full_name')->searchable()->preload(),
                SelectFilter::make('unit_id')->label(__('الوحدة'))->relationship('unit', 'title')->searchable(),
                SelectFilter::make('transaction_type_id')->label(__('النوع'))->relationship('type', 'title'),
                SelectFilter::make('direction')->options(LedgerDirection::class),
                Filter::make('due_date')
                    ->schema([DatePicker::make('from')->label(__('من تاريخ')), DatePicker::make('until')->label(__('إلى تاريخ'))])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $query, $date) => $query->whereDate('due_date', '>=', $date))
                        ->when($data['until'] ?? null, fn (Builder $query, $date) => $query->whereDate('due_date', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('settle')
                    ->label(__('ترحيل'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (OwnerTransaction $record) => ! $record->is_settled && self::allowed('settle'))
                    ->action(function (OwnerTransaction $record) {
                        self::settle([$record]);
                        // legacy finish → offer the payment voucher for the owner straight away
                        if ($record->owner_id && $record->direction === LedgerDirection::Debit && $record->payment_voucher_id === null && self::canCreatePayment()) {
                            Notification::make()->title(__('تم الترحيل'))->success()
                                ->actions([Action::make('voucher')->label(__('إصدار سند صرف للمالك'))
                                    ->url(PaymentVoucherResource::getUrl('create', ['owner' => $record->owner_id, 'entries' => $record->id]))])
                                ->send();
                        }
                    }),
                Action::make('reverse')
                    ->label(__('إلغاء الترحيل'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (OwnerTransaction $record) => $record->is_settled && self::allowed('reverse'))
                    ->action(fn (OwnerTransaction $record) => self::reverse([$record])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('settleSelected')->label(__('ترحيل المحدد'))->icon(Heroicon::OutlinedCheckCircle)
                        ->visible(fn () => self::allowed('settle'))->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => self::settle($records)),
                    BulkAction::make('reverseSelected')->label(__('إلغاء ترحيل المحدد'))->icon(Heroicon::OutlinedArrowUturnLeft)
                        ->visible(fn () => self::allowed('reverse'))->requiresConfirmation()->deselectRecordsAfterCompletion()
                        ->action(fn (Collection $records) => self::reverse($records)),
                    BulkAction::make('receiptVoucher')->label(__('سند قبض من المحدد'))->icon(Heroicon::OutlinedArrowDownOnSquare)
                        ->visible(fn () => self::can('receipt_vouchers.create'))
                        ->action(fn (Collection $records) => self::toVoucher($records, ReceiptVoucherResource::class)),
                    BulkAction::make('paymentVoucher')->label(__('سند صرف من المحدد'))->icon(Heroicon::OutlinedArrowUpOnSquare)
                        ->visible(fn () => self::canCreatePayment())
                        ->action(fn (Collection $records) => self::toVoucher($records, PaymentVoucherResource::class)),
                ]),
            ]);
    }

    /** Legacy finishStat. */
    private static function settle(iterable $records): void
    {
        foreach ($records as $record) {
            if (! $record->is_settled) {
                $record->forceFill(['is_settled' => true, 'settled_at' => now()])->save();
            }
        }
    }

    /** Legacy returnStat; an entry already paid out by a voucher must be released by deleting that voucher. */
    private static function reverse(iterable $records): void
    {
        $blocked = 0;
        foreach ($records as $record) {
            if ($record->payment_voucher_id !== null || $record->receipt_voucher_id !== null) {
                $blocked++;

                continue;
            }
            $record->forceFill(['is_settled' => false, 'settled_at' => null])->save();
        }
        if ($blocked > 0) {
            Notification::make()->title(__(':count حركة مرتبطة بسند — احذف السند أولًا لإلغاء ترحيلها', ['count' => $blocked]))->warning()->send();
        }
    }

    /** Legacy statmin/statmout: open the voucher form with the selected entries of one owner. */
    private static function toVoucher(Collection $records, string $resource)
    {
        $owners = $records->pluck('owner_id')->filter()->unique();
        if ($owners->count() !== 1) {
            Notification::make()->title(__('اختر حركات مالك واحد'))->danger()->send();

            return null;
        }

        return redirect($resource::getUrl('create', ['owner' => $owners->first(), 'entries' => $records->pluck('id')->implode(',')]));
    }

    private static function allowed(string $ability): bool
    {
        return self::can("owner_transactions.{$ability}");
    }

    private static function canCreatePayment(): bool
    {
        return self::can('payment_vouchers.create');
    }

    private static function can(string $permission): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can($permission));
    }
}
