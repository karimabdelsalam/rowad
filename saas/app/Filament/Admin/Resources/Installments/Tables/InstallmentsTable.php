<?php

namespace App\Filament\Admin\Resources\Installments\Tables;

use App\Enums\InstallmentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Installment;
use App\Services\Installments\InstallmentCollector;
use App\Support\Hijri;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

/** Legacy pays_pending + pays_processed screens in one table. */
class InstallmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['unit', 'contract']))
            ->defaultSort('due_date')
            ->columns([
                TextColumn::make('due_date')
                    ->date('Y-m-d')
                    ->description(fn (Installment $record) => $record->calendar?->value === 'hijri' ? Hijri::toString($record->due_date) : null)
                    ->sortable(),
                TextColumn::make('unit.title')->searchable()->sortable(),
                TextColumn::make('contract_label')
                    ->label(__('العقد'))
                    ->state(fn (Installment $record) => $record->contract?->number ?? '#'.$record->contract_id),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount')->numeric(2)->sortable(),
                TextColumn::make('paid_amount')->state(fn (Installment $record) => $record->paidTotal())->numeric(2)->toggleable(),
                TextColumn::make('remaining')
                    ->label(__('المتبقي'))
                    ->state(fn (Installment $record) => $record->remainingAmount())
                    ->numeric(2),
                TextColumn::make('vat_amount')->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('buyer_commission')->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('seller_commission')->numeric(2)->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_collected')->boolean(),
                TextColumn::make('collected_at')->dateTime('Y-m-d')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_collected')->default(false),
                SelectFilter::make('type')->options(InstallmentType::class),
                Filter::make('overdue')
                    ->label(__('متأخرة'))
                    ->query(fn (Builder $query) => $query->where('is_collected', false)->whereDate('due_date', '<', today())),
                Filter::make('due_this_month')
                    ->label(__('مستحقة هذا الشهر'))
                    ->query(fn (Builder $query) => $query->whereBetween('due_date', [today()->startOfMonth(), today()->endOfMonth()])),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('collect')
                    ->label(__('تحصيل'))
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Installment $record) => ! $record->is_collected && self::allowed('collect'))
                    ->action(fn (Installment $record) => self::run(fn () => app(InstallmentCollector::class)->collect($record), __('تم تحصيل القسط'))),
                Action::make('collectPartial')
                    ->label(__('دفعة جزئية'))
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('warning')
                    ->visible(fn (Installment $record) => ! $record->is_collected && self::allowed('partial_collect'))
                    ->schema(fn (Installment $record) => [
                        TextInput::make('amount')
                            ->label(__('المبلغ'))
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->maxValue($record->remainingAmount())
                            ->helperText(__('المتبقي').': '.number_format($record->remainingAmount(), 2)),
                        Toggle::make('close')->label(__('ترحيل القسط بعد الدفعة')),
                        Toggle::make('settle_owners')->label(__('تسوية حساب الملاك لهذا القسط')),
                    ])
                    ->action(fn (Installment $record, array $data) => self::run(
                        fn () => app(InstallmentCollector::class)->collectPartial($record, (float) $data['amount'], null, (bool) $data['close'], (bool) $data['settle_owners']),
                        __('تم تسجيل الدفعة'),
                    )),
                Action::make('uncollect')
                    ->label(__('إرجاع إلى مستحق'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(__('سيُلغى تحصيل القسط وتُحذف حركات الملاك الناتجة عنه.'))
                    ->visible(fn (Installment $record) => $record->is_collected && self::allowed('reset'))
                    ->action(fn (Installment $record) => self::run(fn () => app(InstallmentCollector::class)->uncollect($record), __('تم إرجاع القسط إلى مستحق'))),
                Action::make('resetPartial')
                    ->label(__('إلغاء الدفعات الجزئية'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn (Installment $record) => ! $record->is_collected && (float) $record->paid_amount > 0 && self::allowed('reset'))
                    ->action(fn (Installment $record) => self::run(fn () => app(InstallmentCollector::class)->resetPartial($record), __('تم إلغاء الدفعات الجزئية'))),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('issueInvoice')
                        ->label(__('إصدار فاتورة'))
                        ->icon(Heroicon::OutlinedReceiptPercent)
                        ->requiresConfirmation()
                        ->visible(fn () => self::allowed('invoice'))
                        ->action(function (Collection $records) {
                            try {
                                $invoice = app(\App\Services\Finance\InvoiceService::class)->fromInstallments($records->load(['unit', 'contract.clients']));
                                Notification::make()->title(__('تم إصدار الفاتورة :number', ['number' => $invoice->number]))->success()
                                    ->actions([\Filament\Actions\Action::make('print')->label(__('طباعة'))
                                        ->url(route('documents.print', ['type' => 'invoice', 'id' => $invoice->id]), shouldOpenInNewTab: true)])
                                    ->send();
                            } catch (BusinessRuleException $e) {
                                Notification::make()->title($e->getMessage())->danger()->send();
                            }
                        }),
                    BulkAction::make('collectSelected')
                        ->label(__('تحصيل المحدد'))
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->requiresConfirmation()
                        ->visible(fn () => self::allowed('collect'))
                        ->action(fn (Collection $records) => self::run(
                            fn () => $records->each(fn (Installment $i) => app(InstallmentCollector::class)->collect($i)),
                            __('تم تحصيل الأقساط المحددة'),
                        )),
                ]),
            ]);
    }

    private static function allowed(string $ability): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->is_super_admin || $user->can("installments.{$ability}"));
    }

    private static function run(callable $callback, string $success): void
    {
        try {
            $callback();
            Notification::make()->title($success)->success()->send();
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }
}
