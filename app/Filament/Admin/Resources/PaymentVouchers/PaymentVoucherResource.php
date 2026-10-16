<?php

namespace App\Filament\Admin\Resources\PaymentVouchers;

use App\Filament\Admin\Resources\PaymentVouchers\Pages\CreatePaymentVoucher;
use App\Filament\Admin\Resources\PaymentVouchers\Pages\EditPaymentVoucher;
use App\Filament\Admin\Resources\PaymentVouchers\Pages\ListPaymentVouchers;
use App\Filament\Admin\Resources\PaymentVouchers\Pages\ViewPaymentVoucher;
use App\Filament\Admin\Resources\PaymentVouchers\Schemas\PaymentVoucherForm;
use App\Filament\Admin\Resources\PaymentVouchers\Schemas\PaymentVoucherInfolist;
use App\Filament\Admin\Resources\PaymentVouchers\Tables\PaymentVouchersTable;
use App\Filament\Resource;
use App\Models\PaymentVoucher;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentVoucherResource extends Resource
{
    protected static ?string $model = PaymentVoucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'سند صرف';

    protected static ?string $pluralModelLabel = 'سندات الصرف';

    protected static ?string $permission = 'payment_vouchers';

    public static function form(Schema $schema): Schema
    {
        return PaymentVoucherForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentVoucherInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentVouchersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentVouchers::route('/'),
            'create' => CreatePaymentVoucher::route('/create'),
            'view' => ViewPaymentVoucher::route('/{record}'),
            'edit' => EditPaymentVoucher::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'party_name', 'reason', 'cheque_number'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return __('سند صرف :number', ['number' => $record->number]);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('إلى') => $record->party_name, __('المبلغ') => static::searchMoney($record->amount), __('التاريخ') => static::searchDate($record->issued_at)]);
    }
}
