<?php

namespace App\Filament\Admin\Resources\OwnerTransactions;

use App\Filament\Admin\Resources\OwnerTransactions\Pages\ListOwnerTransactions;
use App\Filament\Admin\Resources\OwnerTransactions\Pages\ViewOwnerTransaction;
use App\Filament\Admin\Resources\OwnerTransactions\Schemas\OwnerTransactionForm;
use App\Filament\Admin\Resources\OwnerTransactions\Schemas\OwnerTransactionInfolist;
use App\Filament\Admin\Resources\OwnerTransactions\Tables\OwnerTransactionsTable;
use App\Models\OwnerTransaction;
use BackedEnum;
use App\Filament\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OwnerTransactionResource extends Resource
{
    protected static ?string $model = OwnerTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'المالية';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'حركة مالك';

    protected static ?string $pluralModelLabel = 'حسابات الملاك';

    protected static ?string $permission = 'owner_transactions';

    public static function form(Schema $schema): Schema
    {
        return OwnerTransactionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return OwnerTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OwnerTransactionsTable::configure($table);
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
            'index' => ListOwnerTransactions::route('/'),
            'view' => ViewOwnerTransaction::route('/{record}'),
        ];
    }
}
