<?php

namespace App\Filament\Admin\Resources\ReceiptVouchers;

use App\Filament\Admin\Resources\ReceiptVouchers\Pages\CreateReceiptVoucher;
use App\Filament\Admin\Resources\ReceiptVouchers\Pages\EditReceiptVoucher;
use App\Filament\Admin\Resources\ReceiptVouchers\Pages\ListReceiptVouchers;
use App\Filament\Admin\Resources\ReceiptVouchers\Pages\ViewReceiptVoucher;
use App\Filament\Admin\Resources\ReceiptVouchers\Schemas\ReceiptVoucherForm;
use App\Filament\Admin\Resources\ReceiptVouchers\Schemas\ReceiptVoucherInfolist;
use App\Filament\Admin\Resources\ReceiptVouchers\Tables\ReceiptVouchersTable;
use App\Models\ReceiptVoucher;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ReceiptVoucherResource extends Resource
{
    protected static ?string $model = ReceiptVoucher::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'سند قبض';

    protected static ?string $pluralModelLabel = 'سندات القبض';

    protected static ?string $permission = 'receipt_vouchers';

    public static function form(Schema $schema): Schema
    {
        return ReceiptVoucherForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReceiptVoucherInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReceiptVouchersTable::configure($table);
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
            'index' => ListReceiptVouchers::route('/'),
            'create' => CreateReceiptVoucher::route('/create'),
            'view' => ViewReceiptVoucher::route('/{record}'),
            'edit' => EditReceiptVoucher::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
