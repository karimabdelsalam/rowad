<?php

namespace App\Filament\Admin\Resources\OnlinePayments;

use App\Filament\Admin\Resources\Installments\InstallmentResource;
use App\Filament\Admin\Resources\OnlinePayments\Pages\ListOnlinePayments;
use App\Filament\Admin\Resources\ReceiptVouchers\ReceiptVoucherResource;
use App\Filament\Resource;
use App\Models\OnlinePayment;
use BackedEnum;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/** E-collection log: every pay link, what the gateway said, and the voucher it produced. */
class OnlinePaymentResource extends Resource
{
    protected static ?string $model = OnlinePayment::class;

    protected static ?string $permission = 'online_payments';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 25;

    protected static ?string $modelLabel = 'سداد إلكتروني';

    protected static ?string $pluralModelLabel = 'المدفوعات الإلكترونية';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['installment.unit', 'installment.contract', 'receiptVoucher']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label(__('أُنشئ'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('installment.unit.title')->label(__('الوحدة'))->weight('medium')
                    ->description(fn (OnlinePayment $r) => $r->installment?->contract?->number)
                    ->url(fn (OnlinePayment $r) => $r->installment ? InstallmentResource::getUrl('view', ['record' => $r->installment]) : null),
                TextColumn::make('installment.due_date')->label(__('استحقاق القسط'))->date('Y-m-d'),
                TextColumn::make('amount')->label(__('المبلغ'))->numeric(2)->weight('bold'),
                TextColumn::make('status')->label(__('الحالة'))->badge()
                    ->formatStateUsing(fn ($state) => __(OnlinePayment::STATUSES[$state] ?? $state))
                    ->color(fn ($state) => match ($state) { 'paid' => 'success', 'failed' => 'danger', 'initiated' => 'warning', default => 'gray' }),
                TextColumn::make('source')->label(__('الوسيلة'))->placeholder('-'),
                TextColumn::make('gateway_id')->label(__('مرجع البوابة'))->copyable()->fontFamily('mono')->size('xs')->placeholder('-'),
                TextColumn::make('paid_at')->label(__('تاريخ السداد'))->dateTime('Y-m-d H:i')->placeholder('-'),
                TextColumn::make('receiptVoucher.number')->label(__('سند القبض'))->placeholder('-')
                    ->url(fn (OnlinePayment $r) => $r->receiptVoucher ? ReceiptVoucherResource::getUrl('view', ['record' => $r->receiptVoucher]) : null)->color('primary'),
                TextColumn::make('failure')->label(__('السبب'))->limit(40)->tooltip(fn ($state) => $state)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('الحالة'))->options(collect(OnlinePayment::STATUSES)->map(fn ($l) => __($l))->all()),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListOnlinePayments::route('/')];
    }
}
