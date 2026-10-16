<?php

namespace App\Filament\Admin\Resources\Invoices;

use App\Filament\Admin\Resources\Invoices\Pages\CreateInvoice;
use App\Filament\Admin\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Admin\Resources\Invoices\Pages\ViewInvoice;
use App\Filament\Admin\Resources\Invoices\Schemas\InvoiceForm;
use App\Filament\Admin\Resources\Invoices\Schemas\InvoiceInfolist;
use App\Filament\Admin\Resources\Invoices\Tables\InvoicesTable;
use App\Filament\Resource;
use App\Models\Invoice;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'فاتورة';

    protected static ?string $pluralModelLabel = 'الفواتير';

    protected static ?string $permission = 'invoices';

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
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
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'view' => ViewInvoice::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer_name', 'customer_vat_number'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return __('فاتورة :number', ['number' => $record->number]);
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([__('العميل') => $record->customer_name, __('الإجمالي') => static::searchMoney($record->total), __('التاريخ') => static::searchDate($record->issue_date)]);
    }
}
